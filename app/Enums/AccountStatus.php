<?php

namespace App\Enums;

/**
 * Customer account status.
 *
 *   Active   — can sign in (self-registered customers start here)
 *   Inactive — created by an admin and not yet activated: cannot sign in until
 *              an admin activates the account
 *   Blocked  — sign-in stopped by the team
 */
enum AccountStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function canSignIn(): bool
    {
        return $this === self::Active;
    }
}
