@extends('layouts.admin')

@section('title', 'KYC Verification')
@section('subtitle', 'Review and approve customer identity verification.')

@use('App\Enums\KycStatus')
@use('App\Support\IndianMobile')

@php
    $filters = [
        ['key' => null, 'label' => 'All', 'count' => $counts['all']],
        ['key' => 'pending_review', 'label' => 'Pending Review', 'count' => $counts['pending_review']],
        ['key' => 'approved', 'label' => 'Approved', 'count' => $counts['approved']],
        ['key' => 'rejected', 'label' => 'Rejected', 'count' => $counts['rejected']],
        ['key' => 'not_submitted', 'label' => 'Not Submitted', 'count' => $counts['not_submitted']],
    ];
    $active = $filter?->key();
@endphp

@section('content')
    <p class="max-w-2xl text-sm text-taupe">Review customer identity verification. KYC awaiting review is listed first, oldest submission first. Identity numbers are shown only on the review page.</p>

    <nav class="mt-6 -mx-3 overflow-x-auto px-3 sm:mx-0 sm:px-0" aria-label="Filter by KYC status">
        <ul class="flex gap-2">
            @foreach ($filters as $item)
                @php $current = $active === $item['key']; @endphp
                <li class="shrink-0">
                    <a href="{{ route('admin.kyc.index', array_filter(['status' => $item['key']])) }}" @if ($current) aria-current="page" @endif
                       @class([
                           'inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-xs font-semibold whitespace-nowrap transition-colors',
                           'border-ad-gold-soft bg-ad-gold-soft text-white' => $current,
                           'border-ad-line bg-white text-ad-ink hover:border-ad-gold-soft' => ! $current,
                       ]) data-kyc-filter="{{ $item['key'] ?? 'all' }}">
                        {{ $item['label'] }}
                        <span @class(['tabular-nums', 'text-white' => $current, 'text-taupe' => ! $current])>{{ $item['count'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <section class="mt-4 rounded-lg border border-line bg-white" aria-label="Customer KYC">
        @if ($customers->isEmpty())
            <p class="px-5 py-8 text-sm text-taupe" data-kyc-empty>No customers {{ $filter ? 'with the status “'.$filter->label().'”' : 'yet' }}.</p>
        @else
            <p class="px-5 pt-3 text-xs text-taupe lg:hidden">Swipe the table sideways to see all columns.</p>
            <div class="overflow-x-auto" data-scroll-table>
                <table class="w-full min-w-[58rem] text-left text-sm" data-table="kyc">
                    <thead class="text-[0.68rem] tracking-[0.14em] text-taupe uppercase">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-semibold">Customer Name</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Mobile</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Email</th>
                            <th scope="col" class="px-5 py-3 font-semibold">KYC Status</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Submitted</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Last Updated</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($customers as $customer)
                            @php
                                $status = KycStatus::tryFrom((string) $customer->kyc_status) ?? KycStatus::Pending;
                                $submittedAt = $customer->submitted_at ? \Illuminate\Support\Carbon::parse($customer->submitted_at) : null;
                                $updatedAt = $customer->kyc_updated_at ? \Illuminate\Support\Carbon::parse($customer->kyc_updated_at) : null;
                            @endphp
                            <tr data-kyc-row="{{ $customer->id }}" data-kyc-row-status="{{ $status->key() }}">
                                <td class="px-5 py-3 font-semibold">{{ $customer->name }}</td>
                                <td class="px-5 py-3 whitespace-nowrap text-taupe">{{ IndianMobile::format($customer->mobile_number) ?? '—' }}</td>
                                <td class="px-5 py-3 whitespace-nowrap text-taupe">{{ $customer->email }}</td>
                                <td class="px-5 py-3"><x-kyc-status :status="$status" variant="admin" /></td>
                                <td class="px-5 py-3 whitespace-nowrap text-taupe">{{ $status !== KycStatus::Pending ? $submittedAt?->format('d M Y') ?? '—' : '—' }}</td>
                                <td class="px-5 py-3 whitespace-nowrap text-taupe">{{ $updatedAt?->format('d M Y, g:i A') ?? '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    @if ($customer->kyc_id)
                                        <a href="{{ route('admin.kyc.show', $customer->kyc_id) }}" @class([
                                            'inline-flex rounded-md px-3 py-1.5 text-xs font-semibold whitespace-nowrap',
                                            'bg-ad-gold-soft text-white hover:opacity-90' => $status === KycStatus::Submitted,
                                            'border border-ad-line hover:border-ad-gold-soft' => $status !== KycStatus::Submitted,
                                        ])>{{ $status === KycStatus::Submitted ? 'Review' : 'View' }}</a>
                                    @else
                                        <span class="text-xs text-taupe">Not started</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($customers->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $customers->links('admin.partials.pagination') }}</div>
            @endif
        @endif
    </section>
@endsection
