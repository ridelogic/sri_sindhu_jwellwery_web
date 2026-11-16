@inject('planService', 'App\Services\GoldSavingPlanService')
@use('App\Support\SiteContact')

@php
    $explore = [
        ['label' => 'Home', 'href' => route('home')],
        ['label' => 'About', 'href' => route('about')],
        ['label' => 'Gold Rate', 'href' => route('gold.rate')],
        ['label' => 'Gold Saving', 'href' => route('gold.saving')],
        ['label' => 'FAQ', 'href' => route('faq')],
        ['label' => 'Contact', 'href' => route('contact')],
    ];

    $savingLinks = array_merge(
        [['label' => 'All Plans', 'href' => route('gold.saving')]],
        array_map(fn (array $plan) => ['label' => $plan['name'], 'href' => $plan['url']], $planService->all()),
    );

    $accountLinks = [
        ['label' => 'Login', 'href' => route('login')],
        ['label' => 'Register', 'href' => route('register')],
    ];

    $contactRows = [
        ['icon' => 'phone', 'label' => 'Phone', 'value' => SiteContact::phone(), 'href' => SiteContact::phoneHref()],
        ['icon' => 'mail', 'label' => 'Email', 'value' => SiteContact::email(), 'href' => SiteContact::emailHref()],
        ['icon' => 'whatsapp', 'label' => 'WhatsApp', 'value' => SiteContact::whatsappHref() ? 'Chat on WhatsApp' : null, 'href' => SiteContact::whatsappHref()],
    ];

    $socialProfiles = SiteContact::social();
    $socials = [
        ['name' => 'instagram', 'label' => 'Instagram'],
        ['name' => 'facebook', 'label' => 'Facebook'],
        ['name' => 'youtube', 'label' => 'YouTube'],
    ];

    $columns = [
        ['title' => 'Explore', 'links' => $explore],
        ['title' => 'Gold Saving', 'links' => $savingLinks],
        ['title' => 'Account', 'links' => $accountLinks],
    ];
@endphp

<footer class="grain relative isolate border-t border-ivory/10 bg-obsidian text-ivory/65" aria-labelledby="footer-heading">
    <h2 id="footer-heading" class="sr-only">Site information</h2>

    <div class="container-site grid grid-cols-2 gap-x-6 gap-y-12 py-16 lg:grid-cols-12 lg:gap-8 lg:py-20">
        {{-- Brand --}}
        <div class="col-span-2 lg:col-span-4 lg:pr-8">
            <x-logo variant="light" />
            <p class="mt-6 max-w-sm text-sm leading-relaxed">
                Fine gold jewellery, chosen with care. Our Gold Saving Scheme makes it simple to save month by month towards the jewellery you love.
            </p>
            <ul class="mt-7 flex gap-3" aria-label="Social media">
                @foreach ($socials as $social)
                    <li>
                        @if ($url = $socialProfiles[$social['name']] ?? null)
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-flex size-11 items-center justify-center rounded-full border border-ivory/15 text-ivory/80 transition hover:border-champagne hover:bg-champagne hover:text-obsidian" aria-label="{{ config('site.name') }} on {{ $social['label'] }}">
                                <x-icon :name="$social['name']" class="size-[1.1rem]" />
                            </a>
                        @else
                            {{-- No profile configured yet: shown as an inactive placeholder --}}
                            <span class="inline-flex size-11 cursor-default items-center justify-center rounded-full border border-ivory/10 text-ivory/30" title="{{ $social['label'] }} — coming soon" aria-label="{{ $social['label'] }} profile coming soon" role="img">
                                <x-icon :name="$social['name']" class="size-[1.1rem]" />
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        @foreach ($columns as $column)
            <nav class="lg:col-span-2" aria-label="{{ $column['title'] }}">
                <h3 class="font-sans text-[0.68rem] font-semibold tracking-[0.3em] text-champagne uppercase">{{ $column['title'] }}</h3>
                <ul class="mt-5 space-y-3 text-sm">
                    @foreach ($column['links'] as $link)
                        <li><a href="{{ $link['href'] }}" class="transition-colors hover:text-gold-bright">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endforeach

        {{-- Contact --}}
        <div class="lg:col-span-2">
            <h3 class="font-sans text-[0.68rem] font-semibold tracking-[0.3em] text-champagne uppercase">Contact</h3>
            <ul class="mt-5 space-y-4 text-sm">
                @foreach ($contactRows as $row)
                    <li class="flex items-start gap-3">
                        <x-icon :name="$row['icon']" class="mt-0.5 size-4 text-champagne" />
                        @if ($row['value'] && $row['href'])
                            <a href="{{ $row['href'] }}" class="break-all transition-colors hover:text-gold-bright" @if ($row['icon'] === 'whatsapp') target="_blank" rel="noopener" @endif>{{ $row['value'] }}</a>
                        @else
                            <span class="text-ivory/45">{{ $row['label'] }}<span class="mt-0.5 block text-xs text-ivory/30">{{ SiteContact::PLACEHOLDER }}</span></span>
                        @endif
                    </li>
                @endforeach
                <li>
                    <a href="{{ route('contact') }}" class="link-line mt-2 text-ivory">
                        Send an Enquiry
                        <x-icon name="arrow-right" class="size-3.5" />
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="border-t border-ivory/10">
        <div class="container-site flex flex-col items-center justify-between gap-3 py-6 text-center text-xs text-ivory/50 sm:flex-row sm:text-left">
            <p>&copy; {{ date('Y') }} {{ config('site.name') }}. All Rights Reserved.</p>
            <p>Gold rates and plan details shown are indicative. Scheme terms apply.</p>
        </div>
    </div>
</footer>
