<?php

namespace App\Enums;

enum KycAuditAction: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case EnteredByAdmin = 'entered_by_admin';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted for review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::EnteredByAdmin => 'Details entered by admin (draft)',
        };
    }

    /** Customer-facing events notify the customer; internal admin drafts do not. */
    public function notifiesCustomer(): bool
    {
        return $this !== self::EnteredByAdmin;
    }
}
