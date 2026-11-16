@extends('layouts.app')

@section('title', 'My Profile | Sri Sindhu Jewellery')
@section('meta_description', 'Your Sri Sindhu Jewellery account details.')
@section('robots', 'noindex, nofollow')

@section('content')
    <x-customer-shell title="My Profile" emphasis="Profile" text="Your account details.">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="border border-line bg-white" aria-labelledby="details-heading">
                <h2 id="details-heading" class="border-b border-line px-6 py-5 text-2xl">Account Details</h2>
                <dl class="divide-y divide-line" data-profile>
                    @foreach ([
                        ['Full Name', $profile['name']],
                        ['Mobile Number', $profile['mobile'] ?? 'Not provided'],
                        ['Email Address', $profile['email']],
                        ['Member Since', $profile['member_since']?->format('d M Y')],
                    ] as [$label, $value])
                        <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm text-taupe">{{ $label }}</dt>
                            <dd class="font-semibold break-all sm:col-span-2">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="border-t border-line px-6 py-4 text-xs leading-relaxed text-taupe">
                    Need to update your details? Please
                    <a href="{{ route('contact', ['enquiry' => 'account']) }}" class="font-semibold text-gold-deep underline underline-offset-4">contact our team</a>.
                </p>
            </section>

            <aside class="h-fit space-y-4">
                <div class="border border-line bg-white px-6 py-5">
                    <p class="text-xs text-taupe">KYC Status</p>
                    <p class="mt-1 text-lg font-semibold" data-kyc-status>{{ $profile['kyc_status'] }}</p>
                    <a href="{{ route('customer.kyc') }}" class="link-line mt-3 text-obsidian">KYC Verification <x-icon name="arrow-right" class="size-3.5" /></a>
                </div>
                <div class="border border-line bg-white px-6 py-5">
                    <p class="text-xs text-taupe">Schemes</p>
                    <p class="mt-1 text-lg font-semibold">{{ $profile['schemes'] }}</p>
                    <a href="{{ route('customer.schemes.index') }}" class="link-line mt-3 text-obsidian">View My Schemes <x-icon name="arrow-right" class="size-3.5" /></a>
                </div>
                <form method="POST" action="{{ route('customer.logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost-dark w-full">Logout</button>
                </form>
            </aside>
        </div>
    </x-customer-shell>
@endsection
