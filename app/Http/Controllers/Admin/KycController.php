<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectKycRequest;
use App\Models\KycDocument;
use App\Models\KycProfile;
use App\Models\User;
use App\Services\KycService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin KYC review. The list shows customer contact details and status only —
 * PAN, Aadhaar and address never appear in the list; they are shown on the
 * detail page to signed-in admins.
 */
class KycController extends Controller
{
    public const PER_PAGE = 20;

    /** Resolved per request — see GoldSavingController for why not via the constructor. */
    private function kyc(): KycService
    {
        return app(KycService::class);
    }

    public function index(Request $request): View
    {
        $filter = KycStatus::fromKey($request->query('status'));

        // Customers without a KYC profile count as "not submitted".
        $rank = "CASE kyc_profiles.kyc_status WHEN 'submitted' THEN 0 WHEN 'rejected' THEN 1 WHEN 'verified' THEN 3 ELSE 2 END";

        $customers = User::query()->customers()
            ->leftJoin('kyc_profiles', 'kyc_profiles.user_id', '=', 'users.id')
            ->select([
                'users.id', 'users.name', 'users.email', 'users.mobile_number', 'users.created_at',
                'kyc_profiles.id as kyc_id', 'kyc_profiles.kyc_status', 'kyc_profiles.submitted_at', 'kyc_profiles.updated_at as kyc_updated_at',
            ])
            ->when($filter === KycStatus::Pending, fn ($query) => $query->where(fn ($q) => $q->whereNull('kyc_profiles.id')->orWhere('kyc_profiles.kyc_status', KycStatus::Pending->value)))
            ->when($filter && $filter !== KycStatus::Pending, fn ($query) => $query->where('kyc_profiles.kyc_status', $filter->value))
            ->orderByRaw($rank)
            ->orderByRaw('CASE WHEN kyc_profiles.kyc_status = ? THEN kyc_profiles.submitted_at END ASC', [KycStatus::Submitted->value]) // oldest waiting first
            ->orderByRaw('COALESCE(kyc_profiles.updated_at, users.created_at) DESC')
            ->orderByDesc('users.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.kyc.index', [
            'customers' => $customers,
            'filter' => $filter,
            'counts' => $this->counts(),
        ]);
    }

    public function show(KycProfile $kyc): View
    {
        $kyc->load(['user:id,name,email,mobile_number,created_at', 'documents', 'reviewer:id,name', 'auditLogs.actor:id,name']);

        return view('admin.kyc.show', [
            'kyc' => $kyc,
            'documentSlots' => KycService::DOCUMENTS,
        ]);
    }

    public function approve(Request $request, KycProfile $kyc): RedirectResponse
    {
        $this->kyc()->approve($kyc, $request->user());

        return redirect()->route('admin.kyc.show', $kyc)->with('status', "KYC for {$kyc->user->name} approved.");
    }

    public function reject(RejectKycRequest $request, KycProfile $kyc): RedirectResponse
    {
        $this->kyc()->reject($kyc, $request->user(), $request->validated('rejection_reason'));

        return redirect()->route('admin.kyc.show', $kyc)->with('status', "KYC for {$kyc->user->name} rejected. The customer can now update and resubmit.");
    }

    public function document(Request $request, KycDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        return $this->kyc()->documentResponse($document, $request->boolean('download'));
    }

    /** @return array<string, int> keyed by workflow key, plus "all" */
    private function counts(): array
    {
        $byStatus = DB::table('kyc_profiles')
            ->join('users', 'users.id', '=', 'kyc_profiles.user_id')
            ->where('users.is_admin', false)
            ->groupBy('kyc_status')
            ->pluck(DB::raw('count(*)'), 'kyc_status');

        $customers = User::query()->customers()->count();
        $withProfile = (int) $byStatus->sum();

        return [
            'all' => $customers,
            'pending_review' => (int) ($byStatus[KycStatus::Submitted->value] ?? 0),
            'approved' => (int) ($byStatus[KycStatus::Verified->value] ?? 0),
            'rejected' => (int) ($byStatus[KycStatus::Rejected->value] ?? 0),
            'not_submitted' => (int) ($byStatus[KycStatus::Pending->value] ?? 0) + ($customers - $withProfile),
        ];
    }
}
