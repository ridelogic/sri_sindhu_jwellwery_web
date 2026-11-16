<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerPlanStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\CustomerPlan;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Read-only list of every customer scheme (customer_plans) with filters.
 * Paid totals and installment counts come from successful payments only;
 * no financial value is calculated or changed here.
 */
class SchemeController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'user' => ['nullable', 'integer'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'plan' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(CustomerPlanStatus::class)],
        ]);

        $mobile = preg_replace('/\D+/', '', (string) ($filters['mobile'] ?? ''));
        $successful = fn ($query) => $query->where('status', PaymentStatus::Success->value);

        $schemes = CustomerPlan::query()
            ->with(['user:id,name,email,mobile_number', 'plan:id,name'])
            ->withCount(['payments as paid_count' => $successful])
            ->withSum(['payments as paid_paise' => $successful], 'amount_paise')
            ->withMax(['payments as last_paid_at' => $successful], 'paid_at')
            ->whereHas('user', fn ($q) => $q->where('is_admin', false))
            ->when($filters['user'] ?? null, fn ($q, $user) => $q->where('user_id', $user))
            ->when(strlen($mobile) >= 3, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('mobile_number', 'like', '%'.substr($mobile, -10).'%')))
            ->when($filters['plan'] ?? null, fn ($q, $plan) => $q->where('plan_id', $plan))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('created_at')->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.schemes.index', [
            'schemes' => $schemes,
            'filters' => $filters,
            'customers' => User::query()->customers()->orderBy('name')->get(['id', 'name']),
            'plans' => Plan::query()->ordered()->get(['id', 'name']),
            'statuses' => CustomerPlanStatus::cases(),
        ]);
    }
}
