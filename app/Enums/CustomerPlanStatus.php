<?php

namespace App\Enums;

enum CustomerPlanStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Matured = 'matured';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Statuses in which the plan still counts towards a customer's open commitments.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Pending, self::Active];
    }
}
