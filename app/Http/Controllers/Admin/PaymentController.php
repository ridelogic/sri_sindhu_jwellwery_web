<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Read-only admin view of payments. Gateway signatures and credentials are
 * never shown; there are no edit or delete actions — payments are permanent.
 */
class PaymentController extends Controller
{
    public const PER_PAGE = 25;

    /** Filter key → statuses. "pending" covers attempts still awaiting an outcome. */
    public const FILTERS = [
        'pending' => [PaymentStatus::Created, PaymentStatus::Pending],
        'success' => [PaymentStatus::Success],
        'failed' => [PaymentStatus::Failed],
        'cancelled' => [PaymentStatus::Cancelled],
    ];

    public function index(Request $request): View
    {
        $filter = array_key_exists((string) $request->query('status'), self::FILTERS) ? $request->query('status') : null;

        $payments = Payment::query()
            ->with(['user:id,name,email', 'customerPlan:id,scheme_number', 'goldTransaction:id,payment_id,gold_grams,purity'])
            ->when($filter, fn ($query) => $query->whereIn('status', array_map(fn ($s) => $s->value, self::FILTERS[$filter])))
            ->latest('initiated_at')->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $byStatus = DB::table('payments')->groupBy('status')->pluck(DB::raw('count(*)'), 'status');
        $counts = ['all' => (int) $byStatus->sum()];
        foreach (self::FILTERS as $key => $statuses) {
            $counts[$key] = (int) collect($statuses)->sum(fn ($s) => $byStatus[$s->value] ?? 0);
        }

        return view('admin.payments.index', compact('payments', 'filter', 'counts'));
    }

    public function show(Payment $payment): View
    {
        $payment->load([
            'user:id,name,email,mobile_number',
            'customerPlan:id,scheme_number,plan_id,monthly_amount,duration_months,total_commitment,installments_total,minimum_amount,maximum_amount,tenure_unit,maximum_tenure,output_type,status',
            'customerPlan.plan:id,name',
            'goldTransaction',
            'events.actor:id,name',
        ]);

        return view('admin.payments.show', ['payment' => $payment]);
    }
}
