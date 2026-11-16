<?php

namespace App\Enums;

/**
 * KYC workflow status. The stored values pre-date Stage 5 and are kept as-is;
 * the workflow names used in URLs, filters and labels map onto them:
 *
 *   pending   → not_submitted  (nothing sent yet, or a saved draft)
 *   submitted → pending_review
 *   verified  → approved
 *   rejected  → rejected
 */
enum KycStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Not Submitted',
            self::Submitted => 'Pending Review',
            self::Verified => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    /** Workflow name used in filters, data attributes and CSS hooks. */
    public function key(): string
    {
        return match ($this) {
            self::Pending => 'not_submitted',
            self::Submitted => 'pending_review',
            self::Verified => 'approved',
            self::Rejected => 'rejected',
        };
    }

    public static function fromKey(?string $key): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->key() === $key) {
                return $case;
            }
        }

        return null;
    }

    /** The customer may change details/documents only before submission or after a rejection. */
    public function isEditableByCustomer(): bool
    {
        return in_array($this, [self::Pending, self::Rejected], true);
    }

    /** Only a submitted KYC can be approved or rejected. */
    public function isAwaitingReview(): bool
    {
        return $this === self::Submitted;
    }
}
