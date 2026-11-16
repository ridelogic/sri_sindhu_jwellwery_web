@extends('layouts.admin')

@section('title', 'Customer Schemes List')
@section('subtitle', 'Every customer scheme with its paid amount and installments. Read-only — open a scheme to see its payments.')

@use('App\Enums\CustomerPlanStatus')
@use('App\Support\IndianMobile')
@use('App\Support\Money')

@php
    $tones = ['active' => 'success', 'pending' => 'warning', 'completed' => 'gold', 'matured' => 'info', 'cancelled' => 'danger'];
    $field = 'block w-full px-3 py-2 text-sm';
    $active = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty();
@endphp

@section('content')
    <x-admin.filter-bar :action="route('admin.schemes.index')" :reset="route('admin.schemes.index')" :active="$active">
        <label class="block">
            <span data-form-label class="mb-1.5 block">User</span>
            <select name="user" class="{{ $field }}">
                <option value="">Select User</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) ($filters['user'] ?? '') === (string) $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span data-form-label class="mb-1.5 block">Mobile No.</span>
            <input type="search" name="mobile" value="{{ $filters['mobile'] ?? '' }}" maxlength="20" inputmode="numeric" placeholder="Search Mobile Number" class="{{ $field }}">
        </label>
        <label class="block">
            <span data-form-label class="mb-1.5 block">Scheme</span>
            <select name="plan" class="{{ $field }}">
                <option value="">Select Scheme</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->id }}" @selected((string) ($filters['plan'] ?? '') === (string) $plan->id)>{{ $plan->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span data-form-label class="mb-1.5 block">Status</span>
            <select name="status" class="{{ $field }}">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status === CustomerPlanStatus::Pending ? 'Awaiting First Payment' : $status->label() }}</option>
                @endforeach
            </select>
        </label>
    </x-admin.filter-bar>

    <x-admin.card :padded="false">
        @if ($schemes->isEmpty())
            <x-admin.empty-state icon="layers" :title="$active ? 'No schemes match these filters.' : 'No customer schemes yet.'" data-schemes-empty />
        @else
            <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-xs text-ad-muted">
                <span>{{ number_format($schemes->total()) }} {{ \Illuminate\Support\Str::plural('scheme', $schemes->total()) }}</span>
                <span class="lg:hidden">Swipe the table sideways to see all columns.</span>
            </div>
            <div class="overflow-x-auto" data-scroll-table>
                <table class="w-full min-w-[64rem] text-left" data-table="customer-schemes">
                    <thead class="text-[0.7rem] uppercase">
                        <tr>
                            <th scope="col" class="px-2.5 font-semibold">#</th>
                            <th scope="col" class="px-2.5 font-semibold">User Profile</th>
                            <th scope="col" class="px-2.5 font-semibold">Scheme Name</th>
                            <th scope="col" class="px-2.5 text-right font-semibold" data-wrap>Join Amount</th>
                            <th scope="col" class="px-2.5 text-right font-semibold">Tenure</th>
                            <th scope="col" class="px-2.5 text-right font-semibold" data-wrap>Total Paid</th>
                            <th scope="col" class="px-2.5 text-center font-semibold" data-wrap>Installments Paid</th>
                            <th scope="col" class="px-2.5 font-semibold">Status</th>
                            <th scope="col" class="px-2.5 font-semibold" data-wrap>Joined Date</th>
                            <th scope="col" class="px-2.5 font-semibold" data-wrap>Maturity Date</th>
                            <th scope="col" class="px-2.5 font-semibold" data-wrap>Actual Complete Date</th>
                            <th scope="col" class="px-2.5 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($schemes as $scheme)
                            @php
                                $completedOn = $scheme->status === CustomerPlanStatus::Completed && $scheme->last_paid_at ? \Illuminate\Support\Carbon::parse($scheme->last_paid_at) : null;
                            @endphp
                            <tr data-scheme-list-row="{{ $scheme->scheme_number }}">
                                <td class="px-2.5 text-ad-muted tabular-nums">{{ $schemes->firstItem() + $loop->index }}</td>
                                <td class="px-2.5">
                                    <a href="{{ route('admin.customers.show', $scheme->user_id) }}" class="font-semibold text-ad-ink hover:text-ad-gold">{{ $scheme->user?->name }}</a>
                                    <span class="block text-xs text-ad-muted">{{ IndianMobile::format($scheme->user?->mobile_number) ?? $scheme->user?->email }}</span>
                                </td>
                                <td class="px-2.5">
                                    <span class="font-semibold whitespace-nowrap text-ad-ink">{{ $scheme->plan?->name }}</span>
                                    <span class="block font-mono text-[0.7rem] whitespace-nowrap">{{ $scheme->scheme_number }}</span>
                                </td>
                                <td class="px-2.5 text-right whitespace-nowrap tabular-nums">{{ $scheme->amountRangeLabel() }}</td>
                                <td class="px-2.5 text-right whitespace-nowrap">{{ $scheme->tenureLabel() }}</td>
                                <td class="px-2.5 text-right font-semibold whitespace-nowrap text-ad-gold tabular-nums" data-scheme-paid>{{ Money::inr(Money::fromPaise((int) $scheme->paid_paise)) }}</td>
                                <td class="px-2.5 text-center whitespace-nowrap tabular-nums" data-scheme-installments>{{ (int) $scheme->paid_count }} / {{ $scheme->installments_total ?? '—' }}</td>
                                <td class="px-2.5"><x-admin.badge :tone="$tones[$scheme->status->value] ?? 'neutral'">{{ $scheme->status === CustomerPlanStatus::Pending ? 'Awaiting Payment' : $scheme->status->label() }}</x-admin.badge></td>
                                <td class="px-2.5 whitespace-nowrap text-ad-ink">{{ $scheme->created_at?->format('d M Y') }}</td>
                                <td class="px-2.5 whitespace-nowrap text-ad-ink">{{ $scheme->maturity_date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-2.5 whitespace-nowrap text-ad-ink">{{ $completedOn?->format('d M Y') ?? '—' }}</td>
                                <td class="px-2.5 text-right">
                                    <a href="{{ route('admin.customers.schemes.show', [$scheme->user_id, $scheme->id]) }}" class="admin-action admin-action-primary">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($schemes->hasPages())
                <div class="border-t border-line px-4 py-3">{{ $schemes->links('admin.partials.pagination') }}</div>
            @endif
        @endif
    </x-admin.card>
@endsection
