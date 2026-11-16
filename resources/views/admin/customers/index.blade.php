@extends('layouts.admin')

@section('title', 'Registered Users')

@use('App\Support\IndianMobile')

@php
    $kycOptions = ['' => 'All', 'not_submitted' => 'Not Submitted', 'pending_review' => 'Pending Review', 'approved' => 'Approved', 'rejected' => 'Rejected'];
    $statusOptions = ['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive', 'blocked' => 'Blocked'];
    $filtered = filled($filters['q'] ?? null) || filled($filters['mobile'] ?? null) || filled($filters['kyc'] ?? null) || filled($filters['status'] ?? null);
@endphp

@section('page-header')
    <x-admin.page-header title="Registered Users">
        <a href="{{ route('admin.customers.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="size-4" />
            Add Customer
        </a>
    </x-admin.page-header>
@endsection

@section('content')
    {{-- Filters (server-side, GET) --}}
    <x-admin.filter-bar :action="route('admin.customers.index')" :reset="route('admin.customers.index')" :active="$filtered" aria-label="Filter registered users">
        <div>
            <label for="filter-user" data-form-label>User</label>
            <input id="filter-user" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Search name or email" class="w-full" data-customer-search>
        </div>
        <div>
            <label for="filter-mobile" data-form-label>Mobile No.</label>
            <input id="filter-mobile" type="search" name="mobile" value="{{ $filters['mobile'] ?? '' }}" maxlength="20" inputmode="numeric" placeholder="Search mobile number" class="w-full" data-customer-mobile>
        </div>
        <div>
            <label for="filter-status" data-form-label>User Status</label>
            <select id="filter-status" name="status" class="w-full">
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filter-kyc" data-form-label>KYC Status</label>
            <select id="filter-kyc" name="kyc" class="w-full">
                @foreach ($kycOptions as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['kyc'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </x-admin.filter-bar>

    <section class="admin-card overflow-hidden" aria-label="Registered users">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ad-line px-3 py-2 text-xs text-ad-muted">
            <p data-customer-counts>
                <strong class="font-semibold text-ad-ink">{{ number_format($counts['all']) }}</strong> customers ·
                {{ number_format($counts['active']) }} active ·@if ($counts['inactive'] > 0) {{ number_format($counts['inactive']) }} inactive ·@endif {{ number_format($counts['blocked']) }} blocked
            </p>
            @if ($customers->total() > 0 && ! $customers->hasPages())
                <p>Showing {{ $customers->firstItem() }}–{{ $customers->lastItem() }} of {{ number_format($customers->total()) }}</p>
            @endif
        </div>

        @if ($customers->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-ad-muted" data-customers-empty>{{ $filtered ? 'No customers match these filters.' : 'No customers yet.' }}</p>
        @else
            <div class="overflow-x-auto" data-scroll-table>
                <table class="admin-grid-table w-full min-w-[66rem] text-left" data-table="customers">
                    <thead class="uppercase">
                        <tr>
                            <th scope="col" class="w-10 text-center">ID</th>
                            <th scope="col" class="w-12 text-center">Profile</th>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Mobile</th>
                            <th scope="col" data-wrap>Registered At</th>
                            <th scope="col" data-wrap>KYC Status</th>
                            <th scope="col" data-wrap>User Status</th>
                            <th scope="col" class="text-center" data-wrap>Active Schemes</th>
                            <th scope="col" data-wrap>Nominee KYC</th>
                            <th scope="col" data-wrap>Nominee Status</th>
                            <th scope="col" class="text-center" data-wrap>View User</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $customer)
                            <tr data-customer-row="{{ $customer->id }}">
                                <td class="text-center text-ad-muted tabular-nums">{{ $customer->id }}</td>
                                <td class="text-center">
                                    {{-- No profile photos are stored: a neutral avatar --}}
                                    <span class="admin-avatar" aria-hidden="true"><x-icon name="user" class="size-3.5" /></span>
                                </td>
                                <td class="min-w-[8rem] font-semibold leading-snug">
                                    <a href="{{ route('admin.customers.show', $customer) }}" class="text-ad-ink hover:text-ad-gold">{{ $customer->name }}</a>
                                </td>
                                <td class="whitespace-nowrap text-ad-muted">{{ $customer->email }}</td>
                                <td class="whitespace-nowrap tabular-nums">{{ IndianMobile::format($customer->mobile_number) ?? '—' }}</td>
                                <td class="whitespace-nowrap text-ad-muted tabular-nums leading-tight">{{ $customer->created_at?->format('d M Y') }}<span class="block text-[0.7rem]">{{ $customer->created_at?->format('H:i') }}</span></td>
                                <td><x-kyc-status :status="$customer->kycProfile?->kyc_status" variant="admin" /></td>
                                <td>@include('admin.customers._status', ['status' => $customer->status])</td>
                                <td class="text-center tabular-nums" data-open-schemes>{{ $customer->open_schemes_count }}</td>
                                {{-- Nominee details are not captured by the application yet: placeholders only. --}}
                                <td class="text-ad-muted" data-nominee-kyc title="No nominee added">—</td>
                                <td class="text-ad-muted" data-nominee-status title="No nominee added">—</td>
                                <td class="text-center">
                                    <a href="{{ route('admin.customers.show', $customer) }}" class="admin-action" aria-label="View {{ $customer->name }}">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($customers->hasPages())
                <div class="border-t border-ad-line px-3 py-2.5">{{ $customers->onEachSide(1)->links('admin.partials.pagination') }}</div>
            @endif
        @endif
    </section>
@endsection
