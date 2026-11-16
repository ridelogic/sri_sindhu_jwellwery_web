<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGoldRateRequest;
use App\Services\GoldRateService;
use App\Services\GoldRateUpdateService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Admin gold rate management. Rates are append-only: there are deliberately
 * no edit or delete actions — every change is a new rate update.
 */
class GoldRateController extends Controller
{
    /** Resolved per request — see GoldSavingController for why not via the constructor. */
    private function rates(): GoldRateService
    {
        return app(GoldRateService::class);
    }

    public function index(): View
    {
        return view('admin.gold-rates.index', [
            'current' => $this->rates()->today(),
            'recentUpdates' => $this->rates()->updates(perPage: 5),
        ]);
    }

    public function create(): View
    {
        $current = $this->rates()->today();

        return view('admin.gold-rates.create', [
            'current' => $current,
            'defaults' => [
                'rate_date' => today()->toDateString(),
                'rate_time' => now()->format('H:i'),
                'source' => GoldRateUpdateService::DEFAULT_SOURCE,
            ],
        ]);
    }

    public function store(StoreGoldRateRequest $request, GoldRateUpdateService $updates): RedirectResponse
    {
        $data = $request->validated();

        $records = $updates->record(
            Carbon::parse($data['rate_date']),
            $data['rate_time'],
            ['22K' => $data['rate_22k'], '24K' => $data['rate_24k']],
            $data['source'] ?? null,
        );

        $summary = $records->map(fn ($r) => $r->purity->value.' '.Money::inr($r->rate_per_gram).'/g')->implode(' · ');

        return redirect()->route('admin.gold-rates.index')
            ->with('status', "Gold rate updated: {$summary}, effective ".$records->first()->effectiveAtMoment()->format('d M Y, g:i A').'.');
    }

    public function history(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [
            'to.after_or_equal' => 'The "to" date must be on or after the "from" date.',
        ]);

        return view('admin.gold-rates.history', [
            'updates' => $this->rates()->updates($filters['from'] ?? null, $filters['to'] ?? null),
            'filters' => $filters,
        ]);
    }
}
