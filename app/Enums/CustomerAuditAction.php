<?php

namespace App\Enums;

enum CustomerAuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Blocked = 'blocked';
    case Unblocked = 'unblocked';
    case Activated = 'activated';
    case Deactivated = 'deactivated';
    case SchemeEnrolled = 'scheme_enrolled';

    public function label(): string
    {
        return match ($this) {
            self::SchemeEnrolled => 'Enrolled in a scheme by admin',
            self::Created => 'Account created by admin',
            self::Updated => 'Details updated',
            self::Blocked => 'Account blocked',
            self::Unblocked => 'Account unblocked',
            self::Activated => 'Account activated',
            self::Deactivated => 'Account deactivated',
        };
    }
}
