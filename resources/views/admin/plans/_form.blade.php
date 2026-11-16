{{--
    Shared create/edit form for plans.
    Expects: $plan (Plan), $action (url), $method ('POST'|'PUT'), $slugLocked (bool), $enrolmentsCount (int),
             $title (string), $back (url), $backLabel (string)
--}}
@php
    $status = (string) old('status', $plan->status ? '1' : '0');
    $featured = (string) (int) old('is_featured', $plan->is_featured);
    $isEdit = $plan->exists;

    $enumValue = fn ($value) => $value instanceof \BackedEnum ? $value->value : $value;
    $schemeType = (string) old('scheme_type', $enumValue($plan->scheme_type) ?? 'saving');
    $tenureUnit = (string) old('tenure_unit', $enumValue($plan->tenure_unit) ?? 'months');
    $outputType = (string) old('output_type', $enumValue($plan->output_type) ?? 'money');
    $bonus = (string) (int) old('bonus_applicable', $plan->bonus_applicable);
    $earlyExit = (string) (int) old('early_exit_allowed', $plan->early_exit_allowed);
    $maxUnlimited = (bool) old('maximum_amount_unlimited', $plan->exists && $plan->maximum_amount === null);
    $frequency = (string) old('contribution_frequency', $enumValue($plan->contribution_frequency) ?? 'monthly');
    $bonusType = (string) old('bonus_type', $enumValue($plan->bonus_type) ?? '');
    $bonusValue = old('bonus_value', $plan->bonus_value !== null ? rtrim(rtrim((string) $plan->bonus_value, '0'), '.') : null);
@endphp
@use('App\Enums\BonusType')
@use('App\Enums\ContributionFrequency')
@use('App\Enums\OutputType')
@use('App\Enums\SchemeType')
@use('App\Enums\TenureUnit')

<form method="POST" action="{{ $action }}" data-plan-form novalidate>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-admin.form-card :title="$title" :back="$back" :back-label="$backLabel">
        @if ($errors->any())
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800" role="alert">
                Please correct the highlighted fields. Nothing has been saved yet.
            </div>
        @endif

        @if ($isEdit && $enrolmentsCount > 0)
            <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900" data-enrolment-warning>
                <strong class="font-semibold">{{ $enrolmentsCount }} existing {{ \Illuminate\Support\Str::plural('enrolment', $enrolmentsCount) }}.</strong>
                Changes apply to new customers only — existing enrolments keep the monthly amount, duration and total they joined with.
            </div>
        @endif

        <x-admin.form-grid>
            {{-- Row 1 --}}
            <x-form.input name="name" label="Plan Name" :value="$plan->name" maxlength="100" required data-plan-name />
            <x-form.select name="scheme_type" label="Scheme Type" :options="SchemeType::options()" :selected="$schemeType" required />

            {{-- Row 2: how often customers contribute, and the tenure unit (two separate settings) --}}
            <x-form.select name="contribution_frequency" label="Contribution Frequency" :options="ContributionFrequency::options()" :selected="$frequency" required />
            <x-form.select name="tenure_unit" label="Plan Tenure Unit" :options="TenureUnit::options()" :selected="$tenureUnit" required data-tenure-unit />

            {{-- Row 3: tenure is stored in the chosen unit — never converted to months --}}
            <div data-tenure-field>
                <x-form.input name="maximum_tenure" type="number" step="1" min="1" inputmode="numeric" :value="$tenureUnit === 'unlimited' ? null : $plan->maximum_tenure"
                    :label="'Maximum Tenure Allowed ('.($tenureUnit === 'unlimited' ? 'not applicable' : $tenureUnit).')'" data-tenure-input
                    :disabled="$tenureUnit === 'unlimited'" :required="$tenureUnit !== 'unlimited'" />
            </div>
            <x-form.input name="minimum_amount" label="Minimum Amount (₹)" type="number" step="0.01" min="0.01" inputmode="decimal" :value="$plan->minimum_amount" required />

            {{-- Row 4 --}}
            <div data-max-amount-field>
                <x-form.input name="maximum_amount" label="Maximum Amount (₹)" type="number" step="0.01" min="0.01" inputmode="decimal"
                    :value="$maxUnlimited ? null : $plan->maximum_amount" data-max-amount-input :disabled="$maxUnlimited" />
                <input type="hidden" name="maximum_amount_unlimited" value="0">
                <label class="mt-1.5 inline-flex cursor-pointer items-center gap-2 text-xs text-ad-muted">
                    <input type="checkbox" name="maximum_amount_unlimited" value="1" class="size-3.5" @checked($maxUnlimited) data-max-amount-unlimited>
                    Unlimited (no upper limit)
                </label>
            </div>
            <x-form.select name="output_type" label="Output" :options="OutputType::options()" :selected="$outputType" required />

            {{-- Row 5: bonus type / value only apply when a bonus is applicable --}}
            <x-form.select name="bonus_applicable" label="Bonus Applicable" :options="['0' => 'No', '1' => 'Yes']" :selected="$bonus" required data-bonus-applicable />
            <x-form.select name="bonus_type" label="Bonus Type" :options="BonusType::options()" :selected="$bonusType" placeholder="Select bonus type" data-bonus-field :disabled="$bonus !== '1'" />

            {{-- Row 6 --}}
            <x-form.input name="bonus_value" label="Bonus Value (% / ₹ / grams per bonus type)" type="number" step="0.000001" min="0" inputmode="decimal"
                :value="$bonus === '1' ? $bonusValue : null" data-bonus-field :disabled="$bonus !== '1'" />
            <x-form.select name="early_exit_allowed" label="Early Exit Allowed" :options="['0' => 'No', '1' => 'Yes']" :selected="$earlyExit" required />

            {{-- Row 7 --}}
            <x-form.select name="status" label="Plan Status" :options="['1' => 'Active', '0' => 'Inactive']" :selected="$status" required />
            <x-form.input name="sort_order" label="Sort Order (lower shows first)" type="number" step="1" min="0" max="9999" :value="$plan->sort_order" required />

            {{-- Website details --}}
            @if ($slugLocked)
                <x-form.input name="slug" label="Slug (URL)" :value="$plan->slug" readonly hint="Locked — customers have joined this plan, so its public URL cannot change." />
            @else
                <x-form.input name="slug" label="Slug (URL)" :value="$plan->slug" maxlength="120" required data-plan-slug :data-slug-auto="$isEdit ? 'false' : 'true'"
                    :hint="$isEdit && $plan->status ? 'Changing the slug changes the public address /gold-saving/'.$plan->slug.' — old links stop working.' : null" />
            @endif
            <x-form.input name="tagline" label="Tagline (optional)" :value="$plan->tagline" maxlength="160" placeholder="One short line shown on plan cards" />

            <x-form.select name="is_featured" label="Highlight as “Most Chosen”" :options="['0' => 'No', '1' => 'Yes']" :selected="$featured" />
        </x-admin.form-grid>

        <x-slot:actions>
            <a href="{{ $isEdit ? route('admin.plans.show', $plan) : route('admin.plans.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Save Changes' : 'Save Scheme' }}</button>
        </x-slot:actions>
    </x-admin.form-card>
</form>
