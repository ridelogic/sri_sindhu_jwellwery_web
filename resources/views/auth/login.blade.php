@extends('layouts.app')

@section('title', 'Login | Sri Sindhu Jewellery')
@section('meta_description', 'Log in to your Sri Sindhu Jewellery account to follow your gold saving plans and payments.')
@section('robots', 'noindex, follow')

@section('content')
    <x-auth-shell
        eyebrow="Customer Account"
        title="Welcome Back."
        emphasis="Back."
        text="Log in with your registered mobile number. We will send you a one-time password (OTP) — no password needed."
        image="images/home/editorial-everyday.jpg"
        quote="Your gold, always within reach."
    >
        <x-notice theme="dark" class="mb-8" />

        @if ($pendingMobile)
            {{-- Step 2: OTP --}}
            @include('auth._otp-step', [
                'verifyRoute' => route('login.verify'),
                'resendRoute' => route('login.resend'),
                'cancelRoute' => route('login.cancel'),
                'cancelLabel' => 'Change mobile number',
                'submitLabel' => 'Verify OTP & Login',
            ])
        @else
            {{-- Step 1: mobile number --}}
            <form method="POST" action="{{ route('login.store') }}" class="space-y-6" novalidate data-login-mobile-form>
                @csrf
                <x-form.input name="mobile" label="Mobile Number" type="tel" theme="dark" inputmode="tel" autocomplete="tel-national" maxlength="16" placeholder="98765 43210" hint="Your 10-digit mobile number registered with Sri Sindhu Jewellery." required autofocus />

                <button type="submit" class="btn btn-gold w-full" data-submit-once>
                    Send OTP
                    <x-icon name="arrow-right" class="size-4" />
                </button>
            </form>
        @endif

        <p class="mt-10 border-t border-ivory/10 pt-8 text-sm text-ivory/70">
            Don't have an account?
            <a href="{{ route('register') }}" class="ml-1 font-semibold text-ivory underline decoration-champagne underline-offset-4 hover:text-gold-bright">Register</a>
            <span class="mt-2 block text-xs text-stone">Changed your mobile number? <a href="{{ route('contact', ['enquiry' => 'account']) }}" class="underline decoration-ivory/30 underline-offset-4 hover:text-gold-bright">Contact us</a> to update it.</span>
        </p>
    </x-auth-shell>
@endsection
