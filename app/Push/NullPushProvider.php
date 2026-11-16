<?php

namespace App\Push;

/**
 * Used when no push provider is configured. It never pretends to deliver.
 */
class NullPushProvider implements PushNotificationProvider
{
    public function name(): string
    {
        return 'none';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function send(string $token, PushMessage $message): PushResult
    {
        return PushResult::notConfigured();
    }
}
