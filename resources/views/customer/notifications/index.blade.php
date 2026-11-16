@extends('layouts.app')

@section('title', 'Notifications | Sri Sindhu Jewellery')
@section('meta_description', 'Your Sri Sindhu Jewellery notifications.')
@section('robots', 'noindex, nofollow')

@section('content')
    <x-customer-shell title="Notifications" emphasis="Notifications" text="Gold rates, payments and KYC updates for your account.">
        <div class="mx-auto max-w-3xl">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-taupe" data-unread-summary>
                    @if ($unread > 0)
                        <strong class="font-semibold text-obsidian">{{ $unread }}</strong> unread
                    @else
                        You're all caught up.
                    @endif
                </p>
                <div class="flex items-center gap-5">
                    @if ($unread > 0)
                        <form method="POST" action="{{ route('customer.notifications.read-all') }}">
                            @csrf
                            <button type="submit" class="link-line text-obsidian" data-read-all>Mark all as read</button>
                        </form>
                    @endif
                    <a href="{{ route('customer.notifications.settings') }}" class="link-line text-gold-deep">Settings</a>
                </div>
            </div>

            @if ($notifications->isEmpty())
                <div class="mt-6 border border-dashed border-line px-6 py-12 text-center" data-empty-notifications>
                    <x-icon name="bell" class="mx-auto size-8 text-gold-deep" />
                    <p class="mt-4 font-serif text-2xl">No notifications yet.</p>
                    <p class="mt-2 text-sm text-taupe">Gold rate updates, payment results and KYC decisions will appear here.</p>
                </div>
            @else
                <ul class="mt-6 divide-y divide-line border border-line bg-ivory" data-notification-list>
                    @foreach ($notifications as $notification)
                        <li><x-notification-item :notification="$notification" /></li>
                    @endforeach
                </ul>

                @if ($notifications->hasPages())
                    <div class="mt-6">{{ $notifications->links() }}</div>
                @endif
            @endif
        </div>
    </x-customer-shell>
@endsection
