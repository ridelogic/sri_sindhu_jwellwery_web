<?php

namespace App\Http\Controllers\Customer;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\CustomerPlan;
use App\Models\Payment;
use App\Payments\PaymentGatewayException;
use App\Payments\PaymentNotAllowedException;
use App\Payments\SimulatorGateway;
use App\Services\GoldCalculationService;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Customer installment payments.
 *
 * Everything financial is derived on the server from the signed-in customer's
 * own scheme: the scheme is looked up by number WITHIN the customer's schemes,
 * payments by reference WITHIN the customer's payments, and the amount comes
 * from customer_plans. The browser only ever sends an idempotency token and
 * the gateway's checkout response, which the gateway adapter verifies.
 */
class PaymentController extends Controller
{
    /** Resolved per request — see GoldSavingController for why not via the constructor. */
    private function payments(): PaymentService
    {
        return app(PaymentService::class);
    }

    public function index(Request $request): View
    {
        return view('customer.payments.index', [
            'payments' => $request->user()->payments()
                ->with(['customerPlan:id,scheme_number,plan_id', 'customerPlan.plan:id,name', 'goldTransaction'])
                ->latest('initiated_at')->latest('id')
                ->paginate(20),
        ]);
    }

    public function create(Request $request, string $schemeNumber): View
    {
        $scheme = $this->scheme($request, $schemeNumber);

        return view('customer.payments.create', [
            'scheme' => $scheme,
            'eligibility' => $this->payments()->eligibility($request->user(), $scheme),
            'purity' => app(GoldCalculationService::class)->purity(),
            'token' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, string $schemeNumber): RedirectResponse
    {
        $scheme = $this->scheme($request, $schemeNumber);
        $data = $request->validate([
            'payment_token' => ['required', 'uuid'],
            // Only flexible-amount schemes send an amount; the service checks it against the scheme's own limits.
            'amount' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:10000000'],
        ], [
            'amount.numeric' => 'Please enter the amount as a number, e.g. 2500.',
            'amount.decimal' => 'The amount can have at most 2 decimal places.',
            'amount.gt' => 'The amount must be greater than zero.',
            'amount.max' => 'The amount looks too high — please check it.',
        ]);
        $amountPaise = filled($data['amount'] ?? null) ? Money::toPaise((string) $data['amount']) : null;

        try {
            $payment = $this->payments()->initiate($request->user(), $scheme, $data['payment_token'], $amountPaise);
        } catch (PaymentNotAllowedException|PaymentGatewayException $e) {
            return redirect()->route('customer.schemes.payment', $scheme->scheme_number)->withErrors(['payment' => $e->getMessage()]);
        }

        return $payment->isSuccessful()
            ? redirect()->route('customer.payments.show', $payment->reference)
            : redirect()->route('customer.payments.checkout', $payment->reference);
    }

    public function checkout(Request $request, string $reference): View|RedirectResponse
    {
        $payment = $this->payment($request, $reference);

        if ($payment->status !== PaymentStatus::Created || ! $payment->gateway_order_id || $payment->gateway !== $this->payments()->gateway()->name()) {
            return redirect()->route('customer.payments.show', $payment->reference);
        }

        $checkout = $this->payments()->gateway()->checkout($payment);

        return view('customer.payments.checkout', [
            'payment' => $payment->load('customerPlan.plan:id,name'),
            'gatewayView' => $checkout['view'],
            'gatewayData' => $checkout['data'],
        ]);
    }

    /** The browser posts the gateway's checkout response here; it is verified server-side. */
    public function callback(Request $request, string $reference): RedirectResponse
    {
        $payment = $this->payment($request, $reference);
        $response = $request->only(['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature']);

        return $this->afterVerification($this->payments()->handleCallback($payment, $response, $request->user()));
    }

    /** Checkout closed or reported a failure. Never creates gold; the customer can retry. */
    public function cancel(Request $request, string $reference): RedirectResponse
    {
        $payment = $this->payment($request, $reference);
        $failed = $request->input('outcome') === 'failed';
        $payment = $this->payments()->cancel($payment, $request->user(), $failed);

        return redirect()->route('customer.payments.show', $payment->reference)->with('status', match ($payment->status) {
            PaymentStatus::Cancelled => 'Payment cancelled. No money was taken. You can try again at any time.',
            PaymentStatus::Failed => 'The payment was not completed. No gold was credited. You can try again.',
            default => 'This payment has already been processed.',
        });
    }

    /** Local development only: complete checkout on the simulated gateway. */
    public function simulate(Request $request, string $reference): RedirectResponse
    {
        $gateway = $this->payments()->gateway();
        abort_unless($gateway instanceof SimulatorGateway && $gateway->isConfigured(), 404);

        $payment = $this->payment($request, $reference);
        abort_unless($payment->gateway === $gateway->name() && $payment->status === PaymentStatus::Created, 404);

        $outcome = $request->validate(['outcome' => ['required', 'in:success,failure']])['outcome'];

        try {
            $response = $gateway->simulate($payment, $outcome === 'success');
        } catch (PaymentGatewayException $e) {
            return redirect()->route('customer.payments.show', $payment->reference)->withErrors(['payment' => $e->getMessage()]);
        }

        // Exactly what a real gateway's browser callback would post — verified the same way.
        return $this->afterVerification($this->payments()->handleCallback($payment, $response, $request->user()));
    }

    public function show(Request $request, string $reference): View
    {
        $payment = $this->payment($request, $reference)->load(['customerPlan.plan:id,name', 'goldTransaction']);

        return view('customer.payments.show', ['payment' => $payment]);
    }

    public function receipt(Request $request, string $reference): View|RedirectResponse
    {
        $payment = $this->payment($request, $reference)->load(['customerPlan.plan:id,name', 'goldTransaction', 'user:id,name']);

        if (! $payment->isSuccessful()) {
            return redirect()->route('customer.payments.show', $payment->reference);
        }

        return view('customer.payments.receipt', ['payment' => $payment]);
    }

    private function afterVerification(Payment $payment): RedirectResponse
    {
        $payment->loadMissing('goldTransaction');

        $message = match ($payment->status) {
            PaymentStatus::Success => $payment->goldTransaction
                ? 'Payment successful. '.$payment->goldTransaction->gold_grams.' g of '.$payment->goldTransaction->purity->value.' gold has been added to your scheme.'
                : 'Payment successful. '.Money::inr($payment->amount()).' has been added to your scheme.',
            PaymentStatus::Pending => 'We are confirming your payment with the bank. This page will show the result once it is confirmed.',
            default => 'The payment could not be completed. No gold was credited. You can try again.',
        };

        return redirect()->route('customer.payments.show', $payment->reference)->with('status', $message);
    }

    private function scheme(Request $request, string $schemeNumber): CustomerPlan
    {
        /** @var CustomerPlan $scheme */
        $scheme = $request->user()->customerPlans()->with('plan:id,name')->where('scheme_number', $schemeNumber)->firstOrFail();
        Gate::authorize('view', $scheme);

        return $scheme;
    }

    private function payment(Request $request, string $reference): Payment
    {
        /** @var Payment $payment */
        $payment = $request->user()->payments()->where('reference', $reference)->firstOrFail();
        Gate::authorize('view', $payment);

        return $payment;
    }
}
