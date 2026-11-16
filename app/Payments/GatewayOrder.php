<?php

namespace App\Payments;

final readonly class GatewayOrder
{
    public function __construct(
        public string $orderId,
        public int $amountPaise,
        public string $currency,
    ) {}
}
