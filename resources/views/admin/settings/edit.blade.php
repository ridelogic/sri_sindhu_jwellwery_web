@extends('layouts.admin')

@section('title', 'Settings')
@section('form_page', '1')

@php
    // Flashed by SettingsController@update after the server has saved (null otherwise).
    $saved = session('settings_saved');
    $savedAt = $saved ? \Illuminate\Support\Carbon::parse($saved['at']) : null;
@endphp

@section('content')
    <form method="POST" action="{{ route('admin.settings.update') }}" data-settings-form novalidate>
        @csrf
        @method('PUT')

        <x-admin.form-card title="Settings">
            @if ($errors->any())
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800" role="alert" data-settings-errors>
                    Please correct the highlighted fields. Nothing has been saved yet.
                </div>
            @endif

            @if (session('settings_error'))
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800" role="alert" data-settings-save-error>
                    {{ session('settings_error') }}
                </div>
            @endif

            @if ($saved)
                {{-- Confirmation of the save the server just completed --}}
                <div class="admin-settings-saved" role="status" data-settings-saved>
                    <span class="admin-settings-saved-tick" aria-hidden="true"><x-icon name="check" class="size-4" stroke="2.5" /></span>
                    <dl class="admin-settings-saved-list">
                        <div><dt>Status</dt><dd data-saved-status>Saved successfully</dd></div>
                        <div><dt>Last saved</dt><dd><time datetime="{{ $savedAt->toIso8601String() }}" data-saved-at>{{ $savedAt->format('d M Y, g:i:s A') }}</time></dd></div>
                        <div><dt>Saved by</dt><dd data-saved-by>{{ $saved['by'] }}</dd></div>
                        <div class="sm:col-span-3">
                            <dt>Settings updated</dt>
                            <dd data-saved-changes>{{ $saved['changed'] ? implode(', ', $saved['changed']) : 'No changes — the values were already up to date.' }}</dd>
                        </div>
                    </dl>
                </div>
            @endif

            <p class="mb-3 text-xs font-semibold tracking-[0.12em] text-ad-muted uppercase">Company &amp; website details</p>

            <x-admin.form-grid>
                <x-form.input name="company_name" :label="$labels['company_name']" :value="$settings['company_name']" maxlength="120" autocomplete="organization" required />
                <x-form.input name="website_name" :label="$labels['website_name']" :value="$settings['website_name']" maxlength="120" autocomplete="off" required />
                <x-form.input name="company_phone" :label="$labels['company_phone']" type="tel" :value="$settings['company_phone']" maxlength="20" autocomplete="tel" placeholder="+91 98765 43210" />
                <x-form.input name="company_email" :label="$labels['company_email']" type="email" :value="$settings['company_email']" maxlength="150" autocomplete="email" placeholder="info@example.com" />
                <x-form.textarea name="company_address" :label="$labels['company_address']" rows="3" maxlength="500" class="md:col-span-2" :value="$settings['company_address']" />
            </x-admin.form-grid>

            <div class="mt-4 space-y-1 rounded-md border border-ad-line bg-ad-panel px-3 py-2.5 text-xs text-ad-muted" data-settings-note>
                <p>
                    Company and website details only — API keys, payment, SMS and other credentials are never shown or stored here.
                    @if ($lastUpdated['at'])
                        Last saved {{ $lastUpdated['at']->format('d M Y, g:i A') }}@if ($lastUpdated['by']) by {{ $lastUpdated['by'] }}@endif.
                    @else
                        Not saved yet — the values shown are the current defaults.
                    @endif
                </p>
                <p data-settings-storage>
                    Stored in the <code class="font-semibold text-ad-ink">{{ $storage['table'] }}</code> table of the application database
                    (connection <code class="font-semibold text-ad-ink">{{ $storage['connection'] }}</code>, database <code class="font-semibold text-ad-ink">{{ $storage['database'] }}</code>), one row per setting.
                </p>
            </div>

            <x-slot:actions>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" data-submit-once data-saving-label="Saving…">Save Settings</button>
            </x-slot:actions>
        </x-admin.form-card>
    </form>
@endsection
