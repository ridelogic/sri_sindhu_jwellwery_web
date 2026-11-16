<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\CustomerAuditAction;
use App\Models\CustomerPlan;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Admin actions on customer ACCOUNTS. Uses the existing users table and
 * customer guard — no separate customer table.
 *
 * Only account details and the active/blocked status change here. Schemes,
 * payments, gold transactions and KYC are never touched. Every action is
 * audited with the changed field NAMES only (never values or passwords).
 */
class CustomerManagementService
{
    /** @param  array{name: string, email: string, mobile_number: string}  $details */
    public function create(User $admin, array $details): User
    {
        $this->assertAdmin($admin);

        // Customers sign in with mobile number + OTP. The password column is required, so it
        // holds a random, never-disclosed value (hashed by the model cast) that nobody knows.
        $password = Str::password(64);

        return DB::transaction(function () use ($admin, $details, $password) {
            // A plain customer: no KYC, schemes, payments or gold are created.
            // Admin-created accounts ALWAYS start Inactive (never taken from the request)
            // and cannot sign in until an admin activates them.
            $customer = new User($details + ['password' => $password]);
            $customer->forceFill(['status' => AccountStatus::Inactive])->save();
            $this->audit($customer, $admin, CustomerAuditAction::Created);

            return $customer;
        });
    }

    /** @param  array{name: string, email: string, mobile_number: string}  $details */
    public function update(User $admin, User $customer, array $details): User
    {
        $this->assertCustomer($admin, $customer);

        return DB::transaction(function () use ($admin, $customer, $details) {
            $customer->fill($details);
            $changed = array_keys($customer->getDirty());

            if ($changed !== []) {
                $customer->save();
                $this->audit($customer, $admin, CustomerAuditAction::Updated, $changed);
            }

            return $customer;
        });
    }

    /**
     * Enrol a customer in a plan on their behalf. All enrolment rules (active
     * plan, snapshot copied from the locked plan row, unique scheme number,
     * duplicate-submission protection) live in EnrolmentService — the same
     * path as customer self-enrolment. Only a NEW enrolment is audited.
     *
     * @return array{0: CustomerPlan, 1: bool} the enrolment and whether it was newly created
     */
    public function enrol(User $admin, User $customer, Plan $plan, ?string $token = null): array
    {
        $this->assertCustomer($admin, $customer);

        [$scheme, $created] = app(EnrolmentService::class)->enrol($customer, $plan, $token);

        if ($created) {
            $this->audit($customer, $admin, CustomerAuditAction::SchemeEnrolled, null, "{$plan->name} · {$scheme->scheme_number} (plan #{$plan->id}, scheme #{$scheme->id})");
        }

        return [$scheme, $created];
    }

    public function block(User $admin, User $customer, ?string $reason = null): User
    {
        return $this->setStatus($admin, $customer, AccountStatus::Blocked, CustomerAuditAction::Blocked, $reason);
    }

    public function unblock(User $admin, User $customer): User
    {
        return $this->setStatus($admin, $customer, AccountStatus::Active, CustomerAuditAction::Unblocked);
    }

    /** Inactive → Active. Only inactive accounts are activated here (Blocked uses Unblock). */
    public function activate(User $admin, User $customer): User
    {
        return $this->transition($admin, $customer, AccountStatus::Inactive, AccountStatus::Active, CustomerAuditAction::Activated);
    }

    /** Active → Inactive: the customer can no longer sign in until re-activated. */
    public function deactivate(User $admin, User $customer): User
    {
        return $this->transition($admin, $customer, AccountStatus::Active, AccountStatus::Inactive, CustomerAuditAction::Deactivated);
    }

    /** Change status only when the account is currently $from (checked on the locked row). */
    private function transition(User $admin, User $customer, AccountStatus $from, AccountStatus $to, CustomerAuditAction $action): User
    {
        $this->assertCustomer($admin, $customer);

        return DB::transaction(function () use ($admin, $customer, $from, $to, $action) {
            $locked = User::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === $from) {
                $locked->forceFill(['status' => $to])->save();
                $this->audit($locked, $admin, $action);
            }

            return $locked;
        });
    }

    private function setStatus(User $admin, User $customer, AccountStatus $status, CustomerAuditAction $action, ?string $note = null): User
    {
        $this->assertCustomer($admin, $customer);

        return DB::transaction(function () use ($admin, $customer, $status, $action, $note) {
            $locked = User::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== $status) {
                $locked->forceFill(['status' => $status])->save();
                $this->audit($locked, $admin, $action, null, $note);
            }

            return $locked;
        });
    }

    /** @param  list<string>|null  $fields */
    private function audit(User $customer, User $admin, CustomerAuditAction $action, ?array $fields = null, ?string $note = null): void
    {
        $customer->customerAuditLogs()->create([
            'actor_id' => $admin->id,
            'action' => $action,
            'fields' => $fields,
            'note' => $note === null ? null : mb_substr($note, 0, 255),
        ]);
    }

    private function assertAdmin(User $admin): void
    {
        if (! $admin->isAdmin()) {
            throw new LogicException('Only administrators can manage customers.');
        }
    }

    /** Admin accounts — including the signed-in admin — are never managed here. */
    private function assertCustomer(User $admin, User $customer): void
    {
        $this->assertAdmin($admin);

        if ($customer->isAdmin() || $customer->is($admin)) {
            throw new LogicException('Administrator accounts cannot be managed as customers.');
        }
    }
}
