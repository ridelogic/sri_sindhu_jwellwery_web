<?php

namespace App\Push;

use App\Enums\DeliveryStatus;

final readonly class PushResult
{
    public function __construct(
        public DeliveryStatus $status,
        public ?string $error = null,
        public bool $invalidToken = false,
    ) {}

    public static function sent(): self
    {
        return new self(DeliveryStatus::Sent);
    }

    public static function failed(string $error, bool $invalidToken = false): self
    {
        return new self(DeliveryStatus::Failed, mb_substr($error, 0, 255), $invalidToken);
    }

    public static function notConfigured(): self
    {
        return new self(DeliveryStatus::NotConfigured, 'Push provider is not configured.');
    }
}
