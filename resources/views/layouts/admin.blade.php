<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Dashboard') · Admin · {{ config('site.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    /*
     * Sidebar. Only working pages are listed (existing routes). An item without a
     * route would render disabled ("Soon"); none are listed at the moment.
     */
    $pendingKyc = \App\Models\KycProfile::query()->where('kyc_status', \App\Enums\KycStatus::Submitted->value)->count();
    $unconfirmedPayments = \App\Models\Payment::query()->where('status', \App\Enums\PaymentStatus::Pending->value)->count();
    $alerts = $pendingKyc + $unconfirmedPayments;

    $navigation = [
        ['label' => 'Dashboard', 'icon' => 'home', 'route' => 'admin.dashboard'],
        ['label' => 'Customers', 'icon' => 'user', 'route' => 'admin.customers.index', 'active' => ['admin.customers.index', 'admin.customers.show', 'admin.customers.edit', 'admin.customers.kyc.*', 'admin.customers.schemes.*']],
        ['label' => 'Gold Rates', 'icon' => 'bars', 'children' => [
            ['label' => 'Current Rates', 'route' => 'admin.gold-rates.index'],
            ['label' => 'Rate History', 'route' => 'admin.gold-rates.history'],
            ['label' => "Update Today's Rate", 'route' => 'admin.gold-rates.create', 'action' => true],
        ]],
        ['label' => 'Scheme Management', 'icon' => 'coins', 'children' => [
            ['label' => 'Customer Schemes List', 'route' => 'admin.schemes.index'],
            ['label' => 'Add Plan', 'route' => 'admin.plans.create', 'action' => true],
        ]],
        ['label' => 'All Plans', 'icon' => 'file', 'route' => 'admin.plans.index', 'active' => ['admin.plans.index', 'admin.plans.show', 'admin.plans.edit']],
        ['label' => 'Payments', 'icon' => 'card', 'route' => 'admin.payments.index', 'active' => 'admin.payments.*'],
        ['label' => 'User Details', 'icon' => 'users', 'children' => [
            ['label' => 'Registered Users', 'route' => 'admin.customers.index', 'active' => ['admin.customers.index', 'admin.customers.show', 'admin.customers.edit', 'admin.customers.kyc.*', 'admin.customers.schemes.*']],
            ['label' => 'User Payment History', 'route' => 'admin.payments.index', 'active' => 'admin.payments.*'],
            ['label' => 'Add Customer', 'route' => 'admin.customers.create', 'action' => true],
        ]],
        ['label' => 'KYC Verification', 'icon' => 'shield', 'route' => 'admin.kyc.index', 'active' => 'admin.kyc.*', 'badge' => $pendingKyc],
        ['label' => 'Push Notification', 'icon' => 'bell', 'route' => 'admin.notifications.index'],
        ['label' => 'Settings', 'icon' => 'cog', 'route' => 'admin.settings.edit', 'active' => 'admin.settings.*'],
    ];

    $sections = [
        'admin.dashboard' => 'Dashboard', 'admin.gold-rates.*' => 'Gold Rates', 'admin.plans.*' => 'Scheme Management',
        'admin.customers.*' => 'User Details', 'admin.schemes.*' => 'Customer Schemes List', 'admin.payments.*' => 'User Payment History',
        'admin.kyc.*' => 'KYC Verification', 'admin.notifications.*' => 'Push Notification',
    ];
    $section = collect($sections)->first(fn ($label, $pattern) => request()->routeIs($pattern)) ?? 'Admin';

    $admin = auth()->user();
    $initial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($admin->name, 0, 1));

    // Inline @section values are already escaped by Blade; decode once so the page header escapes them exactly once.
    $sectionText = fn (string $name, string $default = '') => html_entity_decode(trim($__env->yieldContent($name, $default)), ENT_QUOTES | ENT_HTML5);
    $title = $sectionText('title', 'Dashboard');
    $formPage = $sectionText('form_page') !== ''; // form pages: back button + form card only
@endphp
<body class="admin-ui min-h-screen font-sans antialiased">
    <a href="#admin-main" class="sr-only z-[70] rounded bg-ad-gold-soft px-4 py-2 text-sm font-semibold text-ad-darker focus:not-sr-only focus:fixed focus:top-3 focus:left-3">Skip to content</a>

    <div class="lg:grid lg:grid-cols-[15rem_minmax(0,1fr)] lg:bg-[linear-gradient(to_right,var(--color-ad-dark)_15rem,transparent_15rem)]">
        {{-- Mobile overlay --}}
        <div class="fixed inset-0 z-40 hidden bg-black/50 lg:hidden" data-admin-overlay></div>

        {{-- Sidebar / drawer --}}
        <aside id="admin-sidebar" class="admin-sidebar fixed inset-y-0 left-0 z-50 flex w-[15.5rem] -translate-x-full flex-col transition-transform duration-300 lg:sticky lg:top-0 lg:z-30 lg:h-screen lg:w-auto lg:translate-x-0" data-admin-drawer aria-label="Admin navigation">
            {{-- Brand --}}
            <div class="admin-sidebar-brand">
                <a href="{{ route('admin.dashboard') }}" class="flex min-w-0 items-center gap-3" aria-label="Sri Sindhu Jewellery admin home">
                    <svg class="size-12 shrink-0 text-[#d4af37]" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                        <path d="M20 4c5.2 5.4 7.4 10.7 6.6 16-.6 3.9-2.8 7.2-6.6 10-3.8-2.8-6-6.1-6.6-10C12.6 14.7 14.8 9.4 20 4z" stroke="currentColor" stroke-width="1.4" />
                        <path d="M20 12c2.3 2.7 3.2 5.3 2.7 8-.4 1.9-1.3 3.4-2.7 4.6-1.4-1.2-2.3-2.7-2.7-4.6-.5-2.7.4-5.3 2.7-8z" fill="currentColor" opacity=".85" />
                        <path d="M8 17c4.8.3 8.4 2.6 10.4 6.4 1 2 1.4 4 1.1 6.1-3.9.3-7.1-1-9.3-3.9C8.6 23.4 7.9 20.5 8 17zM32 17c-4.8.3-8.4 2.6-10.4 6.4-1 2-1.4 4-1.1 6.1 3.9.3 7.1-1 9.3-3.9 1.6-2.2 2.3-5.1 2.2-8.6z" stroke="currentColor" stroke-width="1.2" />
                        <path d="M12 34.5h16" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" />
                    </svg>
                    <span class="admin-sidebar-name">Sri Sindhu<span class="block">Jewellery</span></span>
                </a>
                <button type="button" class="ml-auto inline-flex size-9 items-center justify-center rounded-md text-[#d4af37] hover:bg-white/10 lg:hidden" aria-label="Close menu" data-admin-drawer-close>
                    <x-icon name="close" class="size-5" />
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto" aria-label="Admin">
                <ul class="admin-nav-list">
                    @foreach ($navigation as $item)
                        <li>
                            @if (isset($item['route']))
                                @php $current = request()->routeIs($item['active'] ?? $item['route']); @endphp
                                <a href="{{ route($item['route']) }}" class="admin-nav-link" @if ($current) aria-current="page" @endif>
                                    <x-icon :name="$item['icon']" class="admin-nav-icon" />
                                    <span class="min-w-0">{{ $item['label'] }}</span>
                                    @if (! empty($item['badge']))
                                        <span class="ml-auto rounded-full bg-ad-gold-soft px-1.5 py-0.5 text-[0.62rem] font-bold text-obsidian tabular-nums" title="Awaiting review" data-kyc-pending-badge>{{ $item['badge'] }}</span>
                                    @endif
                                    <x-icon name="chevron-right" :class="empty($item['badge']) ? 'admin-nav-chevron ml-auto' : 'admin-nav-chevron'" />
                                </a>
                            @elseif (! empty($item['children']))
                                @php
                                    $sectionActive = collect($item['children'])->contains(fn ($child) => request()->routeIs(...(array) ($child['active'] ?? $child['route'])));
                                @endphp
                                <details class="admin-nav-group" @if ($sectionActive) open @endif>
                                    <summary class="admin-nav-link cursor-pointer select-none">
                                        <x-icon :name="$item['icon']" class="admin-nav-icon" />
                                        <span class="min-w-0">{{ $item['label'] }}</span>
                                        <x-icon name="chevron-right" class="admin-nav-chevron ml-auto transition-transform" />
                                    </summary>
                                    <ul>
                                        @foreach ($item['children'] as $child)
                                            @php $childCurrent = request()->routeIs(...(array) ($child['active'] ?? $child['route'])); @endphp
                                            <li>
                                                <a href="{{ route($child['route']) }}" class="admin-subnav-link" @if ($childCurrent) aria-current="page" @endif>
                                                    @if (! empty($child['action']))<x-icon name="plus" class="size-3.5 text-ad-gold-soft" />@endif
                                                    {{ $child['label'] }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @else
                                <span class="admin-nav-link cursor-default opacity-50 hover:bg-transparent" aria-disabled="true" title="Coming in a later stage">
                                    <x-icon :name="$item['icon']" class="admin-nav-icon" />
                                    <span class="min-w-0">{{ $item['label'] }}</span>
                                    <span class="ml-auto shrink-0 rounded border border-white/20 px-1 py-px text-[0.5rem] font-bold tracking-[0.06em] uppercase">Soon</span>
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="admin-sidebar-footer">
                <a href="{{ route('home') }}" class="admin-nav-link" target="_blank" rel="noopener">
                    <x-icon name="external" class="admin-nav-icon" />
                    <span class="min-w-0">View Website</span>
                    <x-icon name="chevron-right" class="admin-nav-chevron ml-auto" />
                </a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="admin-nav-link w-full text-left">
                        <x-icon name="logout" class="admin-nav-icon" />
                        <span class="min-w-0">Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        {{-- Content column: header, page, footer --}}
        <div class="flex min-h-screen min-w-0 flex-col">
            {{-- Top header (content column): page title left, alerts and admin profile right. Brand shows here only on small screens. --}}
            <header class="admin-topbar sticky top-0 z-40">
                <div class="flex h-14 items-center gap-2 px-3 sm:px-5 lg:px-8">
                    <button type="button" class="inline-flex size-9 items-center justify-center rounded-md text-ad-gold-soft hover:bg-white/5 lg:hidden" aria-label="Open menu" aria-controls="admin-sidebar" aria-expanded="false" data-admin-drawer-open>
                        <x-icon name="menu" class="size-5" />
                    </button>

                    <a href="{{ route('admin.dashboard') }}" class="flex min-w-0 items-center gap-2 lg:hidden" data-admin-brand aria-label="Sri Sindhu Jewellery admin home">
                        <svg class="size-7 shrink-0 text-ad-gold-soft" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                            <circle cx="20" cy="20" r="18.5" stroke="currentColor" stroke-width="1" />
                            <path d="M20 9c3.2 3.6 4.4 7.3 3.6 11-.5 2.4-1.7 4.4-3.6 6-1.9-1.6-3.1-3.6-3.6-6-.8-3.7.4-7.4 3.6-11z" fill="currentColor" />
                            <path d="M11 17.5c3.6.4 6.3 2 8 4.6.9 1.4 1.3 2.8 1 4.4-2.9.4-5.3-.5-7-2.6-1.4-1.8-2-3.9-2-6.4zM29 17.5c-3.6.4-6.3 2-8 4.6-.9 1.4-1.3 2.8-1 4.4 2.9.4 5.3-.5 7-2.6 1.4-1.8 2-3.9 2-6.4z" fill="currentColor" opacity=".55" />
                        </svg>
                        <span class="min-w-0 truncate font-serif text-[1.15rem] leading-none font-semibold text-ad-gold-light">Sri Sindhu Jewellery</span>
                    </a>

                    {{-- Optional page title in the header (pages opt in with @section('header_title')) --}}
                    @hasSection('header_title')
                        <span class="mx-2 hidden h-6 w-px bg-ad-line md:block lg:hidden" aria-hidden="true"></span>
                        <p class="admin-topbar-title hidden md:block" data-header-title>{{ $sectionText('header_title') }}</p>
                    @endif

                    <div class="ml-auto flex items-center gap-1">
                        {{-- Alerts --}}
                        <details class="admin-dropdown relative" data-admin-dropdown>
                            <summary class="relative inline-flex size-9 items-center justify-center rounded-md text-ad-gold-soft hover:bg-white/5" aria-label="Alerts{{ $alerts ? ', '.$alerts.' need attention' : '' }}">
                                <x-icon name="bell" class="size-[1.1rem]" />
                                @if ($alerts > 0)
                                    <span class="absolute top-1 right-1 min-w-[1rem] rounded-full bg-[#b85c5c] px-1 text-center text-[0.58rem] leading-4 font-bold text-white tabular-nums" data-admin-alert-count>{{ $alerts }}</span>
                                @endif
                            </summary>
                            <div class="absolute right-0 mt-2 w-72 max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-md border border-ad-line bg-white text-ad-ink shadow-[0_14px_32px_-14px_rgba(34,38,42,0.45)]">
                                <p class="border-b border-ad-line bg-ad-panel px-4 py-2 text-xs font-bold text-ad-muted">Needs attention</p>
                                <a href="{{ route('admin.kyc.index', ['status' => 'pending_review']) }}" class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm hover:bg-ad-panel">
                                    <span class="flex items-center gap-2"><x-icon name="card" class="size-4 text-ad-gold" /> KYC awaiting review</span>
                                    <span class="font-bold tabular-nums">{{ $pendingKyc }}</span>
                                </a>
                                <a href="{{ route('admin.payments.index', ['status' => 'pending']) }}" class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm hover:bg-ad-panel">
                                    <span class="flex items-center gap-2"><x-icon name="receipt" class="size-4 text-ad-gold" /> Unconfirmed payments</span>
                                    <span class="font-bold tabular-nums">{{ $unconfirmedPayments }}</span>
                                </a>
                            </div>
                        </details>

                        {{-- Admin profile --}}
                        <details class="admin-dropdown relative" data-admin-dropdown>
                            <summary class="flex items-center gap-2 rounded-md py-1 pr-1.5 pl-1 hover:bg-white/5">
                                <span class="inline-flex size-9 items-center justify-center rounded-full bg-ad-dark text-sm font-bold text-white" aria-hidden="true">{{ $initial }}</span>
                                <span class="hidden text-[0.95rem] font-semibold text-ad-ink sm:block">{{ $admin->name }}</span>
                                <x-icon name="chevron-down" class="admin-dropdown-chevron size-4 text-ad-gold-soft transition-transform" />
                            </summary>
                            <div class="absolute right-0 mt-2 w-56 overflow-hidden rounded-md border border-ad-line bg-white py-1 text-ad-ink shadow-[0_14px_32px_-14px_rgba(34,38,42,0.45)]">
                                <p class="border-b border-ad-line px-4 pt-1 pb-2 text-xs text-ad-muted">Signed in as <span class="font-semibold text-ad-ink">{{ $admin->name }}</span></p>
                                <a href="{{ route('home') }}" target="_blank" rel="noopener" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-ad-panel"><x-icon name="arrow-up-right" class="size-4 text-ad-gold" /> View Website</a>
                                <form method="POST" action="{{ route('admin.logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm hover:bg-ad-panel"><x-icon name="arrow-right" class="size-4 rotate-180 text-ad-gold" /> Logout</button>
                                </form>
                            </div>
                        </details>
                    </div>
                </div>
            </header>

            {{-- Pages may opt into a wider content area with @section('wide', '1') (the dashboard does) --}}
            <main id="admin-main" @class(['w-full flex-1 px-3 py-4 sm:px-5', 'lg:px-10 xl:px-[4.75rem]' => $sectionText('wide') === '', 'lg:px-8 lg:py-5' => $sectionText('wide') !== ''])>
                @unless ($formPage)
                    @hasSection('page-header')
                        @yield('page-header')
                    @else
                        <x-admin.page-header :title="$title" :subtitle="$sectionText('subtitle') ?: null"
                            :back="$sectionText('back') ?: null" :back-label="$sectionText('back_label') ?: 'Back'" />
                    @endif
                @endunless

                @if (session('status'))
                    {{-- A controller may flash status_autodismiss to fade the message out after ~5 seconds (Settings does). --}}
                    <div class="mb-4 flex items-start gap-2.5 rounded-md border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-sm text-emerald-900" role="status" data-flash-status @if (session('status_autodismiss')) data-autodismiss="5000" @endif>
                        <x-icon name="check" class="mt-0.5 size-4" stroke="2" />
                        <p>{{ session('status') }}</p>
                    </div>
                @endif
                @yield('content')
            </main>

            <footer @class(['border-t border-ad-line px-3 py-2 text-[0.7rem] text-ad-muted sm:px-5', 'lg:px-10 xl:px-[4.75rem]' => $sectionText('wide') === '', 'lg:px-8' => $sectionText('wide') !== ''])>
                © {{ now()->year }} {{ config('site.name') }} · Administration
            </footer>
        </div>
    </div>
</body>
</html>
