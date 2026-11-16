<?php

namespace App\Policies;

use App\Models\CustomerPlan;
use App\Models\User;

/**
 * A customer may only ever see their own enrolments.
 */
class CustomerPlanPolicy
{
    public function view(User $user, CustomerPlan $customerPlan): bool
    {
        return ! $user->isAdmin() && $customerPlan->user_id === $user->id;
    }
}
