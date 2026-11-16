@extends('layouts.admin')

@section('title', 'Add Scheme')
@section('form_page', '1')

@use('App\Support\Money')

@php $selected = (string) old('plan_id', request('plan')); @endphp

@section('content')
    <form method="POST" action="{{ route('admin.customers.schemes.store', $customer) }}" data-enrol-form>
        @csrf
        <input type="hidden" name="enrolment_token" value="{{ $token }}">

        <x-admin.form-card title="Add Scheme" :back="route('admin.customers.show', $customer)" back-label="Back to Customer">
            @if ($errors->any())
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
            @endif

            @if ($customer->isBlocked())
                <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900">This customer is blocked and cannot sign in. You can still enrol them; they will see the scheme once unblocked.</div>
            @endif

            {{-- Customer (read-only) --}}
            <x-admin.form-grid data-enrol-customer>
                <div>
                    <p data-form-label>Customer Name</p>
                    <div class="admin-input-like flex items-center justify-between gap-2">
                        <span class="truncate font-semibold">{{ $customer->name }}</span>
                        @include('admin.customers._status', ['status' => $customer->status])
                    </div>
                </div>
                <div>
                    <p data-form-label>Email</p>
                    <div class="admin-input-like flex items-center"><span class="truncate">{{ $customer->email }}</span></div>
                </div>
                <div>
                    <p data-form-label>Mobile</p>
                    <div class="admin-input-like flex items-center">{{ $mobile ?? '—' }}</div>
                </div>
                <div>
                    <p data-form-label>Current Schemes</p>
                    <div class="admin-input-like flex items-center tabular-nums">{{ $existing->count() }}</div>
                </div>
            </x-admin.form-grid>

            <fieldset class="mt-5 border-t border-ad-line pt-4">
                <legend class="sr-only">Select Gold Saving Plan</legend>
                <p data-form-label aria-hidden="true">Select Gold Saving Plan <span class="text-ad-gold-soft">*</span></p>
                <p class="mt-0.5 text-xs text-ad-muted">Only active plans can be chosen. The amounts shown are copied into the customer's scheme at enrolment and never change afterwards.</p>

                @if ($plans->isEmpty())
                    <p class="mt-3 rounded-lg border border-dashed border-ad-line bg-white px-4 py-8 text-center text-sm text-ad-muted" data-no-active-plans>There are no active plans. <a href="{{ route('admin.plans.index') }}" class="font-semibold text-ad-gold">Manage plans</a>.</p>
                @else
                    <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($plans as $plan)
                            <label @class([
                                'group relative flex cursor-pointer flex-col rounded-lg border bg-ad-panel p-4 transition-all',
                                'overflow-hidden border-ad-line shadow-[0_6px_18px_-14px_rgba(34,38,42,0.35)] hover:border-ad-gold-soft',
                                'has-[:checked]:border-ad-gold-soft has-[:checked]:shadow-[0_0_0_1px_#c9a55c,0_10px_24px_-18px_rgba(34,38,42,0.45)]',
                                'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-champagne',
                            ]) data-plan-option="{{ $plan->id }}">
                                <span class="absolute inset-x-0 top-0 h-[3px] bg-ad-gold-soft opacity-0 transition-opacity group-has-[:checked]:opacity-100" aria-hidden="true"></span>
                                <input type="radio" name="plan_id" value="{{ $plan->id }}" class="sr-only" @checked($selected === (string) $plan->id) required>

                                <span class="flex items-start justify-between gap-3">
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[0.65rem] font-semibold text-emerald-800 ring-1 ring-emerald-200">Active</span>
                                    {{-- selection indicator in the existing gold accent --}}
                                    <span class="inline-flex size-6 items-center justify-center rounded-full border border-ad-line text-transparent transition-colors group-has-[:checked]:border-ad-gold-soft group-has-[:checked]:bg-ad-gold-soft group-has-[:checked]:text-ad-darker" aria-hidden="true">
                                        <x-icon name="check" class="size-3.5" stroke="2.5" />
                                    </span>
                                </span>

                                <span class="mt-3 block text-lg leading-tight font-bold text-ad-ink">{{ $plan->name }}</span>
                                @if ($plan->tagline)
                                    <span class="mt-1 block text-xs text-ad-gold">{{ $plan->tagline }}</span>
                                @endif

                                <span class="mt-4 block border-y border-ad-line py-3">
                                    <span class="block text-[0.65rem] font-semibold tracking-[0.2em] text-ad-muted uppercase">Monthly contribution</span>
                                    <span class="mt-1 block text-[1.4rem] leading-none font-semibold tabular-nums">{{ $plan->amountRangeLabel() }}</span>
                                </span>
                                <span class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                    <span><span class="block text-xs text-ad-muted">Duration</span><span class="font-semibold">{{ $plan->tenureLabel() }}</span></span>
                                    <span><span class="block text-xs text-ad-muted">Total commitment</span><span class="font-semibold tabular-nums">{{ $plan->total_amount !== null ? Money::inr($plan->total_amount) : 'Open-ended' }}</span></span>
                                </span>

                                @if (! empty($plan->benefits))
                                    <span class="mt-4 block space-y-1.5 text-xs">
                                        @foreach (array_slice($plan->benefits, 0, 3) as $benefit)
                                            <span class="flex gap-2"><x-icon name="check" class="mt-0.5 size-3.5 text-ad-gold" stroke="2" /><span>{{ $benefit['title'] ?? '' }}</span></span>
                                        @endforeach
                                    </span>
                                @endif

                                @if ($plan->terms)
                                    <details class="mt-auto pt-4 text-xs">
                                        <summary class="cursor-pointer font-semibold text-ad-gold">Plan terms</summary>
                                        <p class="mt-2 leading-relaxed whitespace-pre-line text-ad-muted">{{ $plan->terms }}</p>
                                    </details>
                                @endif
                            </label>
                        @endforeach
                    </div>
                @endif
            </fieldset>

            <p class="mt-4 rounded-md border border-ad-line bg-ad-panel px-3 py-2.5 text-xs text-ad-muted">
                The scheme starts as <strong class="text-ad-ink">Awaiting First Payment</strong>. KYC is not required to enrol, but it must be approved before the customer can pay.
            </p>

            <x-slot:actions>
                <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" @disabled($plans->isEmpty()) data-submit-once>Add Scheme</button>
            </x-slot:actions>
        </x-admin.form-card>
    </form>
@endsection
