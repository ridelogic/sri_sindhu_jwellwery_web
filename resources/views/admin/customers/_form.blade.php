{{--
    Customer create/edit form. Expects: $customer (User), $action, $method, $title, $back, $backLabel.
    Only account details — status, KYC and financial records are not editable here.
--}}
@php $isEdit = $customer->exists; @endphp

<form method="POST" action="{{ $action }}" data-customer-form novalidate>
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

        <x-admin.form-grid>
            <x-form.input name="name" label="Full Name" :value="$customer->name" maxlength="100" autocomplete="off" required />
            <x-form.input name="email" label="Email" type="email" :value="$customer->email" maxlength="150" autocomplete="off" required />
            <x-form.input name="mobile" label="Mobile Number" type="tel" inputmode="numeric" :value="$customer->mobile_number" maxlength="20" autocomplete="off" placeholder="98765 43210" required hint="10-digit Indian mobile number." />
            {{-- Display only — status is never submitted. New accounts are always created Inactive (enforced on the server). --}}
            <div>
                <p data-form-label id="account-status-label">Account Status</p>
                <div class="admin-input-like flex items-center" aria-labelledby="account-status-label" aria-describedby="account-status-hint" data-account-status-display>{{ $isEdit ? $customer->status->label() : 'Inactive' }}</div>
                <p id="account-status-hint" class="mt-1.5 text-xs text-ad-muted">{{ $isEdit ? 'Change access with Activate / Block / Unblock on the customer page.' : 'New customer accounts are created Inactive and can sign in once activated.' }}</p>
            </div>

        </x-admin.form-grid>

        <p class="mt-4 rounded-md border border-ad-line bg-ad-panel px-3 py-2.5 text-xs text-ad-muted">
            @if ($isEdit)
                Schemes, payments, gold and KYC are not changed by this form. Account status is changed with Activate / Block / Unblock on the customer page. The customer signs in with this mobile number and a one-time password (OTP).
            @else
                Creates a customer account only — no password is needed. No KYC, scheme, payment or gold record is created. Once activated, the customer signs in with this mobile number and a one-time password (OTP) sent by SMS.
            @endif
        </p>

        <x-slot:actions>
            <a href="{{ $isEdit ? route('admin.customers.show', $customer) : route('admin.customers.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary" data-submit-once>{{ $isEdit ? 'Save Changes' : 'Create Customer' }}</button>
        </x-slot:actions>
    </x-admin.form-card>
</form>
