<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * LOCAL DEVELOPMENT ONLY — a stand-in gateway for exercising the full payment
 * flow without real credentials. It is refused in production.
 *
 * It behaves like a real gateway: it keeps its own record of orders and
 * payments (in the cache), signs checkout responses with a server-side secret,
 * and verification re-checks the signature and its own record — the browser
 * cannot declare a payment successful.
 */
class SimulatorGateway implements PaymentGateway
{
    private const TTL_SECONDS = 86400;

    public function name(): string
    {
        return 'simulator';
    }

    public function isConfigured(): bool
    {
        return ! app()->environment('production');
    }

    public function createOrder(Payment $payment): GatewayOrder
    {
        $this->assertAllowed();

        $orderId = 'sim_order_'.Str::random(14);
        Cache::put($this->key('order', $orderId), ['amount' => $payment->amount_paise, 'currency' => $payment->currency], self::TTL_SECONDS);

        return new GatewayOrder($orderId, $payment->amount_paise, $payment->currency);
    }

    public function checkout(Payment $payment): array
    {
        return ['view' => 'customer.payments.gateways.simulator', 'data' => []];
    }

    /**
     * "The customer completes checkout on the gateway": record the outcome on
     * the gateway side and return the signed response the browser would post back.
     *
     * @return array<string, string>
     */
    public function simulate(Payment $payment, bool $succeed): array
    {
        $this->assertAllowed();

        $order = Cache::get($this->key('order', (string) $payment->gateway_order_id));
        if ($order === null) {
            throw new PaymentGatewayException('This simulated order has expired. Please start the payment again.');
        }

        $paymentId = 'sim_pay_'.Str::random(14);
        Cache::put($this->key('payment', $paymentId), [
            'order_id' => $payment->gateway_order_id,
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'status' => $succeed ? 'captured' : 'failed',
            'method' => 'upi',
            'created_at' => now()->getTimestamp(),
        ], self::TTL_SECONDS);

        return [
            'gateway_order_id' => (string) $payment->gateway_order_id,
            'gateway_payment_id' => $paymentId,
            'gateway_signature' => $this->sign($payment->gateway_order_id, $paymentId),
        ];
    }

    public function verifyPayment(Payment $payment, array $response): GatewayVerification
    {
        $this->assertAllowed();

        $orderId = $response['gateway_order_id'] ?? null;
        $paymentId = $response['gateway_payment_id'] ?? null;
        $signature = $response['gateway_signature'] ?? null;

        if (! is_string($orderId) || ! is_string($paymentId) || ! is_string($signature)) {
            throw new PaymentVerificationException('Incomplete payment response.');
        }
        if (! hash_equals((string) $payment->gateway_order_id, $orderId) || ! hash_equals($this->sign($orderId, $paymentId), $signature)) {
            throw new PaymentVerificationException('The payment signature is invalid.');
        }

        $record = Cache::get($this->key('payment', $paymentId));
        if (! is_array($record) || $record['order_id'] !== $orderId) {
            throw new PaymentVerificationException('The gateway has no record of this payment.');
        }

        $succeeded = $record['status'] === 'captured';

        return new GatewayVerification(
            status: $succeeded ? PaymentStatus::Success : PaymentStatus::Failed,
            orderId: $orderId,
            paymentId: $paymentId,
            amountPaise: (int) $record['amount'],
            currency: (string) $record['currency'],
            method: $record['method'],
            paidAt: Carbon::createFromTimestamp($record['created_at'], config('app.timezone')),
            failureReason: $succeeded ? null : 'The payment was declined (simulated).',
            signature: $signature,
        );
    }

    public function parseWebhook(string $payload, ?string $signature): ?GatewayVerification
    {
        return null; // the simulator has no server-to-server notifications
    }

    public function refundPayment(Payment $payment, ?int $amountPaise = null): string
    {
        $this->assertAllowed();

        return 'sim_rfnd_'.Str::random(14);
    }

    private function sign(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', $orderId.'|'.$paymentId, hash_hmac('sha256', 'payment-simulator', (string) config('app.key')));
    }

    private function key(string $type, string $id): string
    {
        return "payment-simulator:$type:$id";
    }

    private function assertAllowed(): void
    {
        if (! $this->isConfigured()) {
            throw new PaymentGatewayException('Online payments are not available at the moment.');
        }
    }
}
