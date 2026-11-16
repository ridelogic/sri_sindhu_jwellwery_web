<?php

namespace App\Payments;

use App\Models\Payment;

/**
 * A payment gateway adapter. Business rules (eligibility, amounts, gold
 * crediting, idempotency) live in PaymentService; adapters only talk to the
 * gateway and report verified facts back.
 */
interface PaymentGateway
{
    /** Short name stored on payments, e.g. "razorpay". */
    public function name(): string;

    /** True when credentials/mode allow taking payments in this environment. */
    public function isConfigured(): bool;

    /**
     * Open an order for the payment's amount (in paise) with the gateway.
     *
     * @throws PaymentGatewayException
     */
    public function createOrder(Payment $payment): GatewayOrder;

    /**
     * The view (and its public data) that renders this gateway's checkout.
     * Must never include secrets.
     *
     * @return array{view: string, data: array<string, mixed>}
     */
    public function checkout(Payment $payment): array;

    /**
     * Verify a checkout response SERVER-SIDE: signature, order, amount,
     * currency and the payment state reported by the gateway itself.
     *
     * @param  array<string, mixed>  $response  the raw browser callback fields
     *
     * @throws PaymentVerificationException when the response is not authentic or does not match
     * @throws PaymentGatewayException when the gateway cannot be reached
     */
    public function verifyPayment(Payment $payment, array $response): GatewayVerification;

    /**
     * Verify a server-to-server webhook and extract the payment facts it reports.
     * Returns null when the webhook is not authentic or not relevant.
     */
    public function parseWebhook(string $payload, ?string $signature): ?GatewayVerification;

    /**
     * Refund a successful payment (full when $amountPaise is null). Returns the gateway refund id.
     *
     * @throws PaymentGatewayException
     */
    public function refundPayment(Payment $payment, ?int $amountPaise = null): string;
}
