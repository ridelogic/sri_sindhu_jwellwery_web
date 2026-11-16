@extends('layouts.admin')

@section('title', 'Gold Saving Plans')
@section('subtitle', 'Gold saving plans offered to customers. Deactivate a plan to stop offering it — existing enrolments are never changed.')

@use('App\Support\Money')

@section('content')
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-taupe">
            <strong class="font-semibold text-ad-ink">{{ $plans->count() }}</strong> {{ \Illuminate\Support\Str::plural('plan', $plans->count()) }} ·
            {{ $plans->where('status', true)->count() }} active
        </p>
        <a href="{{ route('admin.plans.create') }}" class="btn btn-primary w-full sm:w-auto">
            <x-icon name="plus" class="size-4" />
            Add Plan
        </a>
    </div>

    @if ($plans->isEmpty())
        <div class="admin-card">
            <x-admin.empty-state icon="layers" title="No plans yet." text="Create the first gold saving plan to offer it to customers.">
                <a href="{{ route('admin.plans.create') }}" class="btn btn-primary">Add the first plan</a>
            </x-admin.empty-state>
        </div>
    @else
        <section class="admin-card overflow-hidden" aria-label="Plans">
            <div class="overflow-x-auto" data-scroll-table>
                <table class="w-full min-w-[70rem] text-left" data-table="plans">
                    <thead class="text-[0.7rem] uppercase">
                        <tr>
                            <th scope="col" class="px-2.5 font-semibold">Plan Name</th>
                            <th scope="col" class="px-2.5 font-semibold" data-wrap>Scheme Type</th>
                            <th scope="col" class="px-2.5 font-semibold">Contribution</th>
                            <th scope="col" class="px-2.5 font-semibold">Tenure</th>
                            <th scope="col" class="px-2.5 text-right font-semibold" data-wrap>Amount Range</th>
                            <th scope="col" class="px-2.5 text-right font-semibold">Total</th>
                            <th scope="col" class="px-2.5 font-semibold">Output</th>
                            <th scope="col" class="px-2.5 font-semibold">Bonus</th>
                            <th scope="col" class="px-2.5 font-semibold" data-wrap>Early Exit</th>
                            <th scope="col" class="px-2.5 font-semibold">Status</th>
                            <th scope="col" class="px-2.5 text-right font-semibold">Customers</th>
                            <th scope="col" class="px-2.5 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($plans as $plan)
                            <tr data-plan-row="{{ $plan->slug }}">
                                <td class="px-2.5">
                                    <a href="{{ route('admin.plans.show', $plan) }}" class="font-semibold text-ad-ink hover:text-ad-gold">{{ $plan->name }}</a>
                                    <span class="block font-mono text-[0.7rem] text-taupe">{{ $plan->slug }}</span>
                                    @if ($plan->is_featured)
                                        <x-admin.badge tone="gold" class="mt-1">Most chosen</x-admin.badge>
                                    @endif
                                </td>
                                <td class="px-2.5 whitespace-nowrap" data-plan-scheme-type>{{ $plan->schemeType()->label() }}</td>
                                <td class="px-2.5 whitespace-nowrap" data-plan-frequency>{{ $plan->contributionFrequency()->label() }}</td>
                                <td class="px-2.5 whitespace-nowrap" data-plan-tenure>{{ $plan->tenureLabel() }}</td>
                                <td class="px-2.5 text-right whitespace-nowrap tabular-nums" data-plan-monthly>{{ $plan->amountRangeLabel() }}</td>
                                <td class="px-2.5 text-right whitespace-nowrap tabular-nums" data-plan-total>{{ $plan->total_amount !== null ? Money::inr($plan->total_amount) : '—' }}</td>
                                <td class="px-2.5 whitespace-nowrap" data-plan-output>{{ $plan->outputType()->label() }}</td>
                                <td class="px-2.5 whitespace-nowrap" data-plan-bonus>{{ $plan->bonus_applicable ? ($plan->bonus_type ? $plan->bonus_type->format((string) $plan->bonus_value) : 'Yes') : 'No' }}</td>
                                <td class="px-2.5 whitespace-nowrap">{{ $plan->early_exit_allowed ? 'Yes' : 'No' }}</td>
                                <td class="px-2.5"><x-admin.badge :tone="$plan->status ? 'success' : 'neutral'" data-plan-status>{{ $plan->status ? 'Active' : 'Inactive' }}</x-admin.badge></td>
                                <td class="px-2.5 text-right tabular-nums" data-plan-customers>{{ $plan->customers_count }}</td>
                                <td class="px-2.5 text-right">
                                    <div class="flex justify-end gap-1.5 whitespace-nowrap">
                                        <a href="{{ route('admin.plans.show', $plan) }}" class="admin-action">View</a>
                                        <a href="{{ route('admin.plans.edit', $plan) }}" class="admin-action">Edit</a>
                                        @include('admin.plans._status-actions', ['plan' => $plan])
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
