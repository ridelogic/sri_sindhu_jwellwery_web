<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Razorpay (Standard Checkout) over its REST API — no SDK dependency.
 *
 * Verification never trusts the browser: the checkout signature is checked
 * with the key secret, then the payment is fetched from Razorpay and its
 * order, amount, currency and state are compared with our own record.
 *
 * Mode safety: in "sandbox" only rzp_test_ keys are accepted; in "live" only
 * rzp_live_ keys. The key secret never leaves the server.
 */
class RazorpayGateway implements PaymentGateway
{
    /** @param  array{key: ?string, secret: ?string, webhook_secret: ?string, base_url: string, checkout_script: string, timeout: int}  $config */
    public function __construct(private array $config, private string $environment) {}

    public function name(): string
    {
        return 'razorpay';
    }

    public function isConfigured(): bool
    {
        $key = (string) ($this->config['key'] ?? '');
        $prefix = $this->environment === 'live' ? 'rzp_live_' : 'rzp_test_';

        return $key !== '' && filled($this->config['secret'] ?? null) && str_starts_with($key, $prefix);
    }

    public function createOrder(Payment $payment): GatewayOrder
    {
        $this->assertConfigured();

        $response = $this->send(fn (PendingRequest $http) => $http->post('/orders', [
            'amount' => $payment->amount_paise,
            'currency' => $payment->currency,
            'receipt' => Str::limit($payment->reference, 40, ''),
            'notes' => ['payment_reference' => $payment->reference],
        ]));

        $order = $response->json();

        if (! is_array($order) || empty($order['id']) || (int) ($order['amount'] ?? -1) !== $payment->amount_paise || ($order['currency'] ?? null) !== $payment->currency) {
            throw new PaymentGatewayException('The payment gateway returned an unexpected order. Please try again.');
        }

        return new GatewayOrder((string) $order['id'], (int) $order['amount'], (string) $order['currency']);
    }

    public function checkout(Payment $payment): array
    {
        return [
            'view' => 'customer.payments.gateways.razorpay',
            'data' => [
                'script' => $this->config['checkout_script'],
                'options' => [
                    'key' => $this->config['key'], // public key id — safe for the browser
                    'order_id' => $payment->gateway_order_id,
                    'amount' => $payment->amount_paise,
                    'currency' => $payment->currency,
                    'name' => config('site.name', 'Sri Sindhu Jewellery'),
                    'description' => 'Gold Saving installment '.$payment->installment_number,
                ],
            ],
        ];
    }

    public function verifyPayment(Payment $payment, array $response): GatewayVerification
    {
        $this->assertConfigured();

        $orderId = $response['razorpay_order_id'] ?? null;
        $paymentId = $response['razorpay_payment_id'] ?? null;
        $signature = $response['razorpay_signature'] ?? null;

        if (! is_string($orderId) || ! is_string($paymentId) || ! is_string($signature) || $paymentId === '' || $signature === '') {
            throw new PaymentVerificationException('Incomplete payment response.');
        }

        if (! hash_equals((string) $payment->gateway_order_id, $orderId)) {
            throw new PaymentVerificationException('The payment response does not belong to this order.');
        }

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, (string) $this->config['secret']);
        if (! hash_equals($expected, $signature)) {
            throw new PaymentVerificationException('The payment signature is invalid.');
        }

        // Signature proves Razorpay issued this pair; now ask Razorpay what actually happened.
        $entity = $this->send(fn (PendingRequest $http) => $http->get('/payments/'.rawurlencode($paymentId)))->json();

        return $this->fromEntity($payment->gateway_order_id, is_array($entity) ? $entity : [], $signature, 'callback');
    }

    public function parseWebhook(string $payload, ?string $signature): ?GatewayVerification
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');

        if ($secret === '' || ! is_string($signature) || ! hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
            return null;
        }

        $data = json_decode($payload, true);
        $entity = $data['payload']['payment']['entity'] ?? null;

        if (! in_array($data['event'] ?? null, ['payment.captured', 'payment.failed', 'payment.authorized'], true) || ! is_array($entity) || empty($entity['order_id'])) {
            return null;
        }

        return $this->fromEntity((string) $entity['order_id'], $entity, null, 'webhook');
    }

    public function refundPayment(Payment $payment, ?int $amountPaise = null): string
    {
        $this->assertConfigured();

        $body = $amountPaise === null ? [] : ['amount' => $amountPaise];
        $refund = $this->send(fn (PendingRequest $http) => $http->post('/payments/'.rawurlencode((string) $payment->gateway_payment_id).'/refund', $body))->json();

        return (string) ($refund['id'] ?? throw new PaymentGatewayException('The refund could not be created.'));
    }

    /** Translate a Razorpay payment entity into verified facts, checking it belongs to $orderId. */
    private function fromEntity(?string $orderId, array $entity, ?string $signature, string $source): GatewayVerification
    {
        if (($entity['order_id'] ?? null) === null || $orderId === null || ! hash_equals($orderId, (string) $entity['order_id'])) {
            throw new PaymentVerificationException('The gateway payment does not belong to this order.');
        }

        $status = match ($entity['status'] ?? null) {
            'captured' => PaymentStatus::Success,
            'failed', 'refunded' => PaymentStatus::Failed,
            default => PaymentStatus::Pending, // created / authorized: not money we hold yet
        };

        return new GatewayVerification(
            status: $status,
            orderId: (string) $entity['order_id'],
            paymentId: isset($entity['id']) ? (string) $entity['id'] : null,
            amountPaise: isset($entity['amount']) ? (int) $entity['amount'] : null,
            currency: isset($entity['currency']) ? (string) $entity['currency'] : null,
            method: isset($entity['method']) ? Str::limit((string) $entity['method'], 30, '') : null,
            paidAt: isset($entity['created_at']) ? Carbon::createFromTimestamp((int) $entity['created_at'], config('app.timezone')) : null,
            failureReason: $status === PaymentStatus::Failed
                ? Str::limit((string) ($entity['error_description'] ?? ($entity['status'] === 'refunded' ? 'The payment was refunded.' : 'The payment was declined.')), 200)
                : null,
            signature: $signature,
            source: $source,
        );
    }

    private function send(callable $request): Response
    {
        try {
            $response = $request(
                Http::baseUrl($this->config['base_url'])
                    ->withBasicAuth((string) $this->config['key'], (string) $this->config['secret'])
                    ->acceptJson()
                    ->timeout((int) $this->config['timeout'])
            );
        } catch (ConnectionException) {
            throw new PaymentGatewayException('The payment gateway could not be reached. Please try again.');
        }

        if (! $response->successful()) {
            // Never surface the gateway body: it can echo request details.
            throw new PaymentGatewayException('The payment gateway declined the request. Please try again.');
        }

        return $response;
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new PaymentGatewayException('Online payments are not available at the moment.');
        }
    }
}
