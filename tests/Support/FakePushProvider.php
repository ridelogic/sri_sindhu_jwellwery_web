<?php

namespace Tests\Support;

use App\Push\PushMessage;
use App\Push\PushNotificationProvider;
use App\Push\PushResult;
use RuntimeException;

/**
 * In-memory push provider for tests — no network. Modes: ok, throw, invalid.
 */
class FakePushProvider implements PushNotificationProvider
{
    /** @var list<array{token: string, message: PushMessage}> */
    public array $sent = [];

    public function __construct(public string $mode = 'ok', public bool $configured = true) {}

    public function name(): string
    {
        return 'fake';
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function send(string $token, PushMessage $message): PushResult
    {
        return match ($this->mode) {
            'throw' => throw new RuntimeException('provider exploded'),
            'invalid' => PushResult::failed('Device token is no longer valid.', true),
            default => $this->record($token, $message),
        };
    }

    private function record(string $token, PushMessage $message): PushResult
    {
        $this->sent[] = ['token' => $token, 'message' => $message];

        return PushResult::sent();
    }
}
