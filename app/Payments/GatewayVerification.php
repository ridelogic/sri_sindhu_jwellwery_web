<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use Illuminate\Support\Carbon;

/**
 * Facts about a payment as reported and verified by the gateway itself
 * (never taken from the browser). Status is Success, Pending or Failed.
 */
final readonly class GatewayVerification
{
    public function __construct(
        public PaymentStatus $status,
        public string $orderId,
        public ?string $paymentId = null,
        public ?int $amountPaise = null,
        public ?string $currency = null,
        public ?string $method = null,
        public ?Carbon $paidAt = null,
        public ?string $failureReason = null,
        public ?string $signature = null,
        public string $source = 'callback',
    ) {}
}
