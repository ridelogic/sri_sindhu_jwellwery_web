<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Push\PushMessage;

/**
 * One customer notification: type, title, message, a few labelled details,
 * safe data and a dedupe key. The key identifies the EVENT (e.g. a specific
 * payment event or rate update), so retries can never notify twice while
 * every new event still notifies.
 */
final readonly class NotificationMessage
{
    /**
     * @param  array<string, string>  $details  label => value, shown on the detail page
     * @param  array<string, scalar|null>  $data  non-sensitive structured data
     */
    public function __construct(
        public NotificationType $type,
        public string $title,
        public string $message,
        public string $key,
        public array $details = [],
        public array $data = [],
        public ?string $url = null,
    ) {}

    /** Stored in notifications.data (JSON). */
    public function payload(): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'details' => $this->details,
            'data' => $this->data,
            'url' => $this->url,
        ];
    }

    public function toPush(string $notificationId): PushMessage
    {
        return new PushMessage($this->title, $this->message, array_filter([
            'type' => $this->type->value,
            'notification_id' => $notificationId,
            'url' => (string) $this->url,
        ]));
    }
}
