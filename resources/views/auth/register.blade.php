@extends('layouts.app')

@section('title', 'Create an Account | Sri Sindhu Jewellery')
@section('meta_description', 'Create your Sri Sindhu Jewellery account to begin your gold saving journey.')
@section('robots', 'noindex, follow')

@use('App\Support\Money')

@section('content')
    <x-auth-shell
        eyebrow="Join Gold Saving"
        title="Create Your Account."
        emphasis="Account."
        text="Register and choose your gold saving plan. You can join more plans from your dashboard at any time."
        image="images/home/editorial-bridal.jpg"
        quote="Save today. Celebrate tomorrow."
    >
        <x-notice theme="dark" class="mb-8" />

        @if ($pendingMobile)
            {{-- Step 2: verify the mobile number. The account is created only after this. --}}
            @include('auth._otp-step', [
                'verifyRoute' => route('register.verify'),
                'resendRoute' => route('register.resend'),
                'cancelRoute' => route('register.cancel'),
                'cancelLabel' => 'Change my details',
                'submitLabel' => 'Verify OTP & Create Account',
            ])
        @elseif (empty($plans))
            <div class="border border-ivory/15 px-5 py-5 text-sm leading-relaxed text-ivory/80" data-no-plans>
                Online enrolment is not available at the moment. Please
                <a href="{{ route('contact', ['enquiry' => 'gold-saving']) }}" class="font-semibold text-ivory underline decoration-champagne underline-offset-4">contact our team</a>
                to join a gold saving plan.
            </div>
        @else
            <form method="POST" action="{{ route('register.store') }}" class="space-y-8" novalidate data-register-form>
                @csrf

                {{-- 1. Plan --}}
                <fieldset>
                    <legend class="text-[0.66rem] font-semibold tracking-[0.24em] text-stone uppercase">1 · Choose your plan <span class="text-champagne">*</span></legend>
                    <div class="mt-4 space-y-3" role="radiogroup">
                        @foreach ($plans as $plan)
                            <label class="block cursor-pointer border border-ivory/15 px-5 py-4 transition-colors hover:border-ivory/40 has-[:checked]:border-champagne has-[:checked]:bg-ivory/5" data-plan-option="{{ $plan['slug'] }}">
                                <span class="flex items-start gap-4">
                                    <input type="radio" name="plan" value="{{ $plan['slug'] }}" class="mt-1.5 size-4 shrink-0 accent-champagne" @checked($selectedSlug === $plan['slug']) required>
                                    <span class="min-w-0 flex-1">
                                        <span class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                            <span class="font-serif text-2xl text-ivory">{{ $plan['name'] }}</span>
                                            <span class="text-sm font-semibold text-gold-bright">{{ $plan['amount_label'] }}@if ($plan['per_period'])<span class="font-normal text-stone"> / {{ $plan['per_period'] }}</span>@endif</span>
                                        </span>
                                        <span class="mt-2 grid grid-cols-2 gap-3 text-xs text-stone">
                                            <span>Duration<span class="block text-sm text-ivory/85">{{ $plan['tenure_label'] }}</span></span>
                                            <span>Total commitment<span class="block text-sm text-ivory/85">{{ ($plan['total_amount'] !== null ? Money::inr($plan['total_amount']) : 'Open-ended') }}</span></span>
                                        </span>
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('plan')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
                    <p class="mt-3 text-xs leading-relaxed text-stone">Amounts are confirmed by our system when your account is created. Detailed scheme terms are shared at enrolment.</p>
                </fieldset>

                {{-- 2. Details --}}
                <fieldset class="border-t border-ivory/10 pt-8">
                    <legend class="sr-only">Your details</legend>
                    <p class="text-[0.66rem] font-semibold tracking-[0.24em] text-stone uppercase">2 · Your details</p>
                    <div class="mt-5 grid gap-6 sm:grid-cols-2">
                        <x-form.input name="name" label="Full Name" theme="dark" autocomplete="name" maxlength="100" required class="sm:col-span-2" />
                        <x-form.input name="mobile" label="Mobile Number" type="tel" theme="dark" autocomplete="tel-national" inputmode="tel" maxlength="16" hint="10-digit Indian mobile number" required />
                        <x-form.input name="email" label="Email Address" type="email" theme="dark" autocomplete="email" maxlength="150" required />
                    </div>
                    <p class="mt-4 text-xs leading-relaxed text-stone">No password needed — we will send a one-time password (OTP) to your mobile number to verify it. You will also use your mobile number and an OTP to log in.</p>
                </fieldset>

                <x-form.checkbox name="terms" theme="dark">
                    I agree to the Terms &amp; Conditions and understand that the full Gold Saving Scheme terms will be shared with me at enrolment.
                </x-form.checkbox>

                <button type="submit" class="btn btn-gold w-full" data-submit-once>
                    Send OTP
                    <x-icon name="arrow-right" class="size-4" />
                </button>
            </form>
        @endif

        <p class="mt-10 border-t border-ivory/10 pt-8 text-sm text-ivory/70">
            Already have an account?
            <a href="{{ route('login') }}" class="ml-1 font-semibold text-ivory underline decoration-champagne underline-offset-4 hover:text-gold-bright">Login</a>
        </p>
    </x-auth-shell>
@endsection
