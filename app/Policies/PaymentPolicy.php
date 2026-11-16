<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Payments: admins may view any; a customer only their own.
 */
class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return $user->isAdmin() || $user->id === $payment->user_id;
    }
}
