<?php

namespace App\Push;

/**
 * Provider-neutral push payload. Contains only what a customer may see —
 * never identity numbers, tokens or credentials.
 */
final readonly class PushMessage
{
    /** @param  array<string, string>  $data  string values only (FCM requirement) */
    public function __construct(
        public string $title,
        public string $body,
        public array $data = [],
    ) {}
}
