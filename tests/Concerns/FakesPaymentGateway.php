<?php

namespace Tests\Concerns;

use App\Enums\KycStatus;
use App\Models\CustomerPlan;
use App\Models\GoldRate;
use App\Models\KycProfile;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * A fake Razorpay for automated tests: the real RazorpayGateway adapter runs,
 * but its HTTP calls are answered by Http::fake() and no request can leave
 * the test. Test credentials are obviously fake.
 */
trait FakesPaymentGateway
{
    protected const GATEWAY_KEY = 'rzp_test_FakeKeyForTests';

    protected const GATEWAY_SECRET = 'test-secret-NEVER-SHOWN-9f2c';

    protected const WEBHOOK_SECRET = 'test-webhook-secret-71ab';

    /** @var array<string, array> Razorpay's own record of payments, keyed by payment id */
    protected array $gatewayPayments = [];

    protected function fakeGateway(): void
    {
        config([
            'payments.gateway' => 'razorpay',
            'payments.environment' => 'sandbox',
            'payments.gateways.razorpay.key' => self::GATEWAY_KEY,
            'payments.gateways.razorpay.secret' => self::GATEWAY_SECRET,
            'payments.gateways.razorpay.webhook_secret' => self::WEBHOOK_SECRET,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'api.razorpay.com/v1/orders' => fn (Request $request) => Http::response([
                'id' => 'order_'.Str::random(14),
                'amount' => $request['amount'],
                'currency' => $request['currency'],
                'status' => 'created',
            ]),
            'api.razorpay.com/v1/payments/*' => function (Request $request) {
                $id = basename(parse_url($request->url(), PHP_URL_PATH));

                return isset($this->gatewayPayments[$id])
                    ? Http::response($this->gatewayPayments[$id])
                    : Http::response(['error' => ['code' => 'BAD_REQUEST_ERROR']], 400);
            },
        ]);
    }

    /**
     * The customer completes checkout on Razorpay: record the payment on the
     * gateway side and return the browser callback Razorpay would produce.
     *
     * @return array{razorpay_order_id: string, razorpay_payment_id: string, razorpay_signature: string}
     */
    protected function gatewayPays(Payment $payment, string $status = 'captured', ?int $amountPaise = null, ?string $paymentId = null): array
    {
        $paymentId ??= 'pay_'.Str::random(14);

        $this->gatewayPayments[$paymentId] = [
            'id' => $paymentId,
            'entity' => 'payment',
            'order_id' => $payment->gateway_order_id,
            'amount' => $amountPaise ?? $payment->amount_paise,
            'currency' => 'INR',
            'status' => $status,
            'method' => 'upi',
            'created_at' => now()->getTimestamp(),
            'error_description' => $status === 'failed' ? 'Payment declined by the bank.' : null,
        ];

        return [
            'razorpay_order_id' => (string) $payment->gateway_order_id,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => hash_hmac('sha256', $payment->gateway_order_id.'|'.$paymentId, self::GATEWAY_SECRET),
        ];
    }

    protected function webhookBody(Payment $payment, string $paymentId, string $event = 'payment.captured'): string
    {
        return json_encode([
            'event' => $event,
            'payload' => ['payment' => ['entity' => $this->gatewayPayments[$paymentId]]],
        ]);
    }

    protected function approvedCustomer(array $attributes = []): User
    {
        $customer = User::factory()->create($attributes);
        KycProfile::factory()->for($customer)->create(['kyc_status' => KycStatus::Verified, 'verified_at' => now()]);

        return $customer;
    }

    /** A scheme enrolled at ₹5,000 × 11 starting today. */
    protected function schemeFor(User $customer, string $monthly = '5000', int $months = 11, array $attributes = []): CustomerPlan
    {
        $plan = Plan::factory()->create(['name' => 'Gold Premium', 'monthly_amount' => $monthly, 'duration_months' => $months]);

        return CustomerPlan::factory()->for($customer)->for($plan)->pending()->create($attributes + ['start_date' => today()->toDateString()]);
    }

    protected function rateToday(float $perGram, string $time = '09:30:00'): GoldRate
    {
        return GoldRate::factory()->rate($perGram)->on(today()->toDateString(), $time)->create();
    }
}
