{{--
    OTP entry step (sign-in and registration).
    Expects: $verifyRoute, $resendRoute, $cancelRoute, $cancelLabel, $maskedMobile, $resendIn (seconds), $submitLabel
    The number being verified is held server-side; this form submits only the code.
--}}
@php
    // LOCAL DEVELOPMENT ONLY: shown when SMS_DRIVER=development and APP_ENV is local/testing.
    $developmentCode = app()->environment(['local', 'testing']) && config('sms.driver') === 'development' ? session('otp_development_code') : null;
@endphp

<div data-otp-step>
    @if (session('status'))
        <div class="mb-6 flex items-start gap-4 border-l-2 border-champagne bg-ivory/5 px-5 py-4 text-sm leading-relaxed text-ivory/85" role="status" data-otp-sent>
            <x-icon name="sparkle" class="mt-0.5 size-4 text-champagne" />
            <p>{{ session('status') }}</p>
        </div>
    @endif

    <p class="text-sm leading-relaxed text-ivory/80">
        Enter the 6-digit OTP sent to <strong class="font-semibold text-ivory" data-otp-mobile>{{ $maskedMobile }}</strong>.
    </p>

    @if ($developmentCode)
        <div class="mt-5 border border-dashed border-champagne/70 px-4 py-3 text-sm text-gold-bright" role="note" data-otp-development>
            <span class="block text-[0.62rem] font-semibold tracking-[0.22em] text-champagne uppercase">Development only — no SMS was sent</span>
            OTP: <span class="font-mono text-lg tracking-[0.3em] text-ivory">{{ $developmentCode }}</span>
        </div>
    @endif

    <form method="POST" action="{{ $verifyRoute }}" class="mt-6 space-y-6" novalidate data-otp-form>
        @csrf
        <x-form.input name="otp" label="One-Time Password (OTP)" theme="dark" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" required autofocus placeholder="••••••" />

        <button type="submit" class="btn btn-gold w-full" data-submit-once>
            {{ $submitLabel }}
            <x-icon name="arrow-right" class="size-4" />
        </button>
    </form>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-4 text-sm">
        <form method="POST" action="{{ $resendRoute }}">
            @csrf
            <button type="submit" class="font-semibold text-ivory underline decoration-champagne underline-offset-4 transition-colors hover:text-gold-bright disabled:cursor-not-allowed disabled:text-stone disabled:no-underline"
                data-resend-countdown="{{ (int) $resendIn }}" @disabled($resendIn > 0)>
                <span data-resend-label>{{ $resendIn > 0 ? 'Resend OTP in '.$resendIn.'s' : 'Resend OTP' }}</span>
            </button>
        </form>
        <form method="POST" action="{{ $cancelRoute }}">
            @csrf
            <button type="submit" class="text-ivory/70 underline decoration-ivory/30 underline-offset-4 transition-colors hover:text-gold-bright">{{ $cancelLabel }}</button>
        </form>
    </div>
</div>
