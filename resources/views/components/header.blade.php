@php
    // Only a CUSTOMER-guard session changes the header. An admin (web guard) is a guest here.
    $customer = \Illuminate\Support\Facades\Auth::guard('customer')->user();
    $customer = $customer && ! $customer->isAdmin() ? $customer : null;
    $unreadNotifications = $customer ? $customer->unreadNotifications()->count() : 0; // from the database, never hard-coded

    $accountLinks = [
        ['label' => 'Dashboard', 'route' => 'customer.dashboard', 'active' => 'customer.dashboard'],
        ['label' => 'My Schemes', 'route' => 'customer.schemes.index', 'active' => 'customer.schemes.*'],
        ['label' => 'KYC', 'route' => 'customer.kyc', 'active' => 'customer.kyc*'],
        ['label' => 'Payments', 'route' => 'customer.payments.index', 'active' => 'customer.payments.*'],
        ['label' => 'Profile', 'route' => 'customer.profile', 'active' => 'customer.profile'],
    ];

    $links = [
        ['label' => 'Home', 'route' => 'home'],
        ['label' => 'About', 'route' => 'about'],
        // Gold Rate is shown on the homepage; /gold-rate (history) is linked from there, not the main nav.
        ['label' => 'Gold Saving', 'route' => 'gold.saving', 'active' => 'gold.saving*'],
        ['label' => 'FAQ', 'route' => 'faq'],
        ['label' => 'Contact', 'route' => 'contact'],
    ];

    foreach ($links as &$link) {
        $link['href'] = route($link['route']);
        $link['current'] = request()->routeIs($link['active'] ?? $link['route']);
    }
    unset($link);
@endphp

<header class="site-header fixed inset-x-0 top-0 z-50 text-ivory transition-[background-color,box-shadow] duration-500" data-site-header data-scrolled="false" data-menu-open="false">
    <div class="container-site header-bar flex h-[4.5rem] items-center justify-between gap-4 transition-[height] duration-500 lg:h-24">
        <div class="flex items-center gap-1">
            <button type="button" class="-ml-2.5 inline-flex size-11 items-center justify-center text-ivory xl:hidden" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-nav" data-nav-toggle>
                <x-icon name="menu" class="size-6" data-icon-open />
                <x-icon name="close" class="hidden size-6" data-icon-close />
            </button>
            <x-logo variant="light" />
        </div>

        <nav class="hidden xl:block" aria-label="Main navigation">
            <ul class="flex items-center gap-9">
                @foreach ($links as $link)
                    <li>
                        <a href="{{ $link['href'] }}" @if ($link['current']) aria-current="page" @endif
                           @class([
                               'relative py-2 text-[0.7rem] font-semibold tracking-[0.18em] uppercase transition-colors after:absolute after:inset-x-0 after:-bottom-0.5 after:h-px after:origin-left after:bg-champagne after:transition-transform after:duration-500 hover:text-ivory hover:after:scale-x-100',
                               'text-ivory after:scale-x-100' => $link['current'],
                               'text-ivory/75 after:scale-x-0' => ! $link['current'],
                           ])>{{ $link['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @if ($customer)
            <div class="flex items-center gap-1 sm:gap-5" data-customer-nav>
                <a href="{{ route('customer.notifications.index') }}" @class(['relative inline-flex size-11 items-center justify-center transition-colors hover:text-ivory sm:order-last', 'text-ivory' => request()->routeIs('customer.notifications.*'), 'text-ivory/80' => ! request()->routeIs('customer.notifications.*')])
                   aria-label="Notifications{{ $unreadNotifications ? ', '.$unreadNotifications.' unread' : '' }}" data-notification-bell>
                    <x-icon name="bell" class="size-[1.3rem]" />
                    @if ($unreadNotifications > 0)
                        <span class="absolute top-1 right-0.5 min-w-[1.15rem] rounded-full bg-champagne px-1 text-center text-[0.6rem] leading-[1.15rem] font-bold text-obsidian tabular-nums" data-unread-count>{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                    @endif
                </a>
                <a href="{{ route('customer.dashboard') }}" class="inline-flex size-11 items-center justify-center text-ivory lg:hidden" aria-label="My dashboard">
                    <x-icon name="user" class="size-[1.3rem]" />
                </a>
                <ul class="hidden items-center gap-6 lg:flex">
                    @foreach ($accountLinks as $item)
                        <li>
                            <a href="{{ route($item['route']) }}" @if (request()->routeIs($item['active'])) aria-current="page" @endif
                               @class([
                                   'text-[0.7rem] font-semibold tracking-[0.18em] uppercase transition-colors hover:text-ivory',
                                   'text-ivory' => request()->routeIs($item['active']),
                                   'text-ivory/75' => ! request()->routeIs($item['active']),
                               ])>{{ $item['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('customer.logout') }}" class="hidden sm:block">
                    @csrf
                    <button type="submit" class="btn btn-ghost-light min-h-11 px-5">Logout</button>
                </form>
            </div>
        @else
            <div class="flex items-center gap-1 sm:gap-5">
                <a href="{{ route('login') }}" class="inline-flex size-11 items-center justify-center text-ivory sm:hidden" aria-label="Login">
                    <x-icon name="user" class="size-[1.3rem]" />
                </a>
                <a href="{{ route('login') }}" class="hidden items-center gap-2 text-[0.7rem] font-semibold tracking-[0.18em] text-ivory/80 uppercase transition-colors hover:text-ivory sm:inline-flex">
                    <x-icon name="user" class="size-4" />
                    Login
                </a>
                <a href="{{ route('gold.saving') }}" class="btn btn-gold hidden min-h-11 px-5 sm:inline-flex">Join Gold Saving</a>
            </div>
        @endif
    </div>

    {{-- Mobile / tablet navigation --}}
    <div id="mobile-nav" class="mobile-nav xl:hidden" data-mobile-nav data-open="false">
        <div class="overflow-hidden">
            <nav class="container-site max-h-[calc(100svh-4.5rem)] overflow-y-auto border-t border-ivory/10 pt-4 pb-8" aria-label="Mobile navigation">
                <ul>
                    @foreach ($links as $link)
                        <li class="border-b border-ivory/10">
                            <a href="{{ $link['href'] }}" @if ($link['current']) aria-current="page" @endif
                               @class([
                                   'flex items-center justify-between py-4 font-serif text-[1.65rem] transition-colors hover:text-gold-bright',
                                   'text-gold-bright' => $link['current'],
                                   'text-ivory' => ! $link['current'],
                               ])>
                                {{ $link['label'] }}
                                <x-icon name="arrow-right" class="size-4 text-champagne" />
                            </a>
                        </li>
                    @endforeach
                </ul>
                @if ($customer)
                    <div class="mt-7 border-t border-ivory/10 pt-5">
                        <p class="text-[0.65rem] font-semibold tracking-[0.24em] text-champagne uppercase">My Account</p>
                        <ul class="mt-3 grid grid-cols-2 gap-x-4">
                            @foreach ($accountLinks as $item)
                                <li><a href="{{ route($item['route']) }}" class="block py-2.5 text-ivory/85 hover:text-gold-bright">{{ $item['label'] }}</a></li>
                            @endforeach
                            <li><a href="{{ route('customer.notifications.index') }}" class="flex items-center gap-2 py-2.5 text-ivory/85 hover:text-gold-bright">Notifications @if ($unreadNotifications > 0)<span class="rounded-full bg-champagne px-1.5 text-[0.65rem] font-bold text-obsidian">{{ $unreadNotifications }}</span>@endif</a></li>
                        </ul>
                        <form method="POST" action="{{ route('customer.logout') }}" class="mt-4">
                            @csrf
                            <button type="submit" class="btn btn-ghost-light w-full">Logout</button>
                        </form>
                    </div>
                @else
                    <div class="mt-7 grid gap-3 min-[400px]:grid-cols-2">
                        <a href="{{ route('gold.saving') }}" class="btn btn-gold">Join Gold Saving</a>
                        <a href="{{ route('login') }}" class="btn btn-ghost-light">Login</a>
                    </div>
                @endif
            </nav>
        </div>
    </div>
</header>
