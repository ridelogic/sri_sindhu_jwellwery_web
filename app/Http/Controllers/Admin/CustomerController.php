<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\CustomerPlanStatus;
use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerRequest;
use App\Models\GoldTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Services\CustomerAccountService;
use App\Services\CustomerManagementService;
use App\Services\GoldCalculationService;
use App\Support\IndianMobile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin customer management. {customer} only ever resolves to a NON-admin
 * user (see the route binding), so admin accounts — including the signed-in
 * admin — can never be viewed, edited or blocked here.
 *
 * Read-only for financial and KYC data: schemes, payments, gold and KYC are
 * displayed (masked where sensitive) but never changed by this controller.
 */
class CustomerController extends Controller
{
    public const PER_PAGE = 20;

    public const KYC_FILTERS = ['not_submitted', 'pending_review', 'approved', 'rejected'];

    private function customers(): CustomerManagementService
    {
        return app(CustomerManagementService::class);
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'kyc' => ['nullable', 'in:'.implode(',', self::KYC_FILTERS)],
            'status' => ['nullable', 'in:active,inactive,blocked'],
        ]);

        $search = trim($filters['q'] ?? '');
        $mobile = preg_replace('/\D+/', '', $search);
        // Mobile No. filter: digits only, partial match; a full number (+91 / leading 0 allowed) matches its last 10 digits.
        $mobileFilter = preg_replace('/\D+/', '', (string) ($filters['mobile'] ?? ''));
        $mobileFilter = strlen($mobileFilter) > 10 ? substr($mobileFilter, -10) : $mobileFilter;
        $kyc = KycStatus::fromKey($filters['kyc'] ?? null);

        $customers = User::query()->customers()
            ->with('kycProfile:id,user_id,kyc_status')
            ->withCount(['customerPlans as open_schemes_count' => fn ($q) => $q->whereIn('status', array_map(fn ($s) => $s->value, CustomerPlanStatus::open()))])
            ->when($search !== '', function ($query) use ($search, $mobile) {
                $query->where(function ($q) use ($search, $mobile) {
                    $q->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                    if (strlen($mobile) >= 3) {
                        $q->orWhere('mobile_number', 'like', '%'.substr($mobile, -10).'%');
                    }
                });
            })
            ->when($mobileFilter !== '', fn ($q) => $q->where('mobile_number', 'like', '%'.$mobileFilter.'%'))
            ->when(filled($filters['mobile'] ?? null) && $mobileFilter === '', fn ($q) => $q->whereRaw('1 = 0')) // non-numeric input matches nobody
            ->when($kyc === KycStatus::Pending, fn ($q) => $q->where(fn ($q) => $q->whereDoesntHave('kycProfile')->orWhereHas('kycProfile', fn ($p) => $p->where('kyc_status', KycStatus::Pending->value))))
            ->when($kyc && $kyc !== KycStatus::Pending, fn ($q) => $q->whereHas('kycProfile', fn ($p) => $p->where('kyc_status', $kyc->value)))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('created_at')->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'filters' => $filters,
            'counts' => [
                'all' => User::query()->customers()->count(),
                'active' => User::query()->customers()->where('status', AccountStatus::Active->value)->count(),
                'blocked' => User::query()->customers()->where('status', AccountStatus::Blocked->value)->count(),
                'inactive' => User::query()->customers()->where('status', AccountStatus::Inactive->value)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.customers.create', ['customer' => new User]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = $this->customers()->create($request->user(), $request->details());

        return redirect()->route('admin.customers.show', $customer)->with('status', "Customer “{$customer->name}” created as Inactive. Once activated, they sign in with their mobile number and a one-time password (OTP).");
    }

    public function show(User $customer, CustomerAccountService $accounts, GoldCalculationService $gold): View
    {
        $schemes = $accounts->schemes($customer);
        $successful = Payment::query()->where('user_id', $customer->id)->successful();

        return view('admin.customers.show', [
            'customer' => $customer->load(['kycProfile.documents', 'customerAuditLogs.actor:id,name']),
            'mobile' => IndianMobile::format($customer->mobile_number),
            'schemes' => $schemes,
            'progress' => $accounts->progressFor($schemes),
            'payments' => [
                'count' => (clone $successful)->count(),
                'total_paise' => (int) (clone $successful)->sum('amount_paise'),
                'recent' => Payment::query()->where('user_id', $customer->id)
                    ->with(['customerPlan:id,scheme_number', 'goldTransaction:id,payment_id,gold_grams'])
                    ->latest('initiated_at')->latest('id')->limit(10)->get(),
            ],
            'gold' => [
                'grams' => $gold->sumGrams(GoldTransaction::query()->where('user_id', $customer->id)->pluck('gold_grams')),
                'count' => GoldTransaction::query()->where('user_id', $customer->id)->count(),
            ],
        ]);
    }

    public function edit(User $customer): View
    {
        return view('admin.customers.edit', ['customer' => $customer]);
    }

    public function update(CustomerRequest $request, User $customer): RedirectResponse
    {
        $this->customers()->update($request->user(), $customer, $request->details());

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Customer details updated.');
    }

    public function block(Request $request, User $customer): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null;

        $this->customers()->block($request->user(), $customer, $reason);

        return redirect()->route('admin.customers.show', $customer)->with('status', "{$customer->name} is blocked and can no longer sign in. Their schemes, payments and gold are unchanged.");
    }

    public function unblock(Request $request, User $customer): RedirectResponse
    {
        $this->customers()->unblock($request->user(), $customer);

        return redirect()->route('admin.customers.show', $customer)->with('status', "{$customer->name} is active again and can sign in.");
    }

    /** Newly created (Inactive) customers can sign in only after activation. */
    public function activate(Request $request, User $customer): RedirectResponse
    {
        $this->customers()->activate($request->user(), $customer);

        return redirect()->route('admin.customers.show', $customer)->with('status', "{$customer->name} is now active and can sign in.");
    }

    public function deactivate(Request $request, User $customer): RedirectResponse
    {
        $this->customers()->deactivate($request->user(), $customer);

        return redirect()->route('admin.customers.show', $customer)->with('status', "{$customer->name} is now inactive and cannot sign in until activated. Their schemes, payments and gold are unchanged.");
    }
}
