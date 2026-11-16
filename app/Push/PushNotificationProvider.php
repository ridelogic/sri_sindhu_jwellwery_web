<?php

namespace App\Push;

/**
 * A push transport (e.g. Firebase Cloud Messaging). Business code never talks
 * to a provider directly — it goes through PushNotificationService.
 */
interface PushNotificationProvider
{
    /** Short name stored on delivery records, e.g. "fcm". */
    public function name(): string;

    /** True only when credentials are present and usable. */
    public function isConfigured(): bool;

    /** Send one message to one device token. Must never throw for delivery problems. */
    public function send(string $token, PushMessage $message): PushResult;
}
