@extends('layouts.app')

@section('title', 'Notification Settings | Sri Sindhu Jewellery')
@section('meta_description', 'Choose which notifications you receive.')
@section('robots', 'noindex, nofollow')

@section('content')
    <x-customer-shell title="Notification Settings" emphasis="Settings" text="Choose what you would like to hear about.">
        <div class="mx-auto max-w-2xl space-y-6">
            <a href="{{ route('customer.notifications.index') }}" class="link-line text-obsidian">
                <x-icon name="arrow-right" class="size-3.5 rotate-180" />
                All Notifications
            </a>

            <form method="POST" action="{{ route('customer.notifications.settings.update') }}" class="border border-line bg-white" data-preferences-form>
                @csrf
                @method('PUT')
                <h2 class="border-b border-line px-6 py-5 text-2xl">Notify me about</h2>
                <ul class="divide-y divide-line">
                    @foreach ($keys as $key)
                        <li>
                            <label class="flex cursor-pointer items-start justify-between gap-5 px-6 py-5">
                                <span>
                                    <span class="block font-semibold">{{ $key->label() }}</span>
                                    <span class="mt-1 block text-sm text-taupe">{{ $key->description() }}</span>
                                </span>
                                <input type="hidden" name="{{ $key->value }}" value="0">
                                <input type="checkbox" name="{{ $key->value }}" value="1" @checked($preferences[$key->value])
                                       class="mt-1 size-5 shrink-0 cursor-pointer accent-[#8a6a2f]" data-preference="{{ $key->value }}">
                            </label>
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-line px-6 py-5">
                    <button type="submit" class="btn btn-dark w-full sm:w-auto" data-submit-once>Save Preferences</button>
                </div>
            </form>

            <section class="border border-line bg-white" aria-labelledby="devices-heading">
                <h2 id="devices-heading" class="border-b border-line px-6 py-5 text-2xl">Push Notification Devices</h2>
                @if ($devices->isEmpty())
                    <p class="px-6 py-6 text-sm leading-relaxed text-taupe" data-no-devices>No devices are registered. Phone notifications will be available with the Sri Sindhu Jewellery app; you will always see your notifications here.</p>
                @else
                    <ul class="divide-y divide-line" data-devices>
                        @foreach ($devices as $device)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                                <div class="min-w-0">
                                    <p class="font-semibold">{{ $device->device_name ?: $device->platform->label() }}</p>
                                    <p class="text-xs text-taupe">{{ $device->platform->label() }} · Last active {{ $device->last_used_at?->format('d M Y') ?? '—' }}</p>
                                </div>
                                <form method="POST" action="{{ route('customer.devices.deactivate', $device->id) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-red-700 underline underline-offset-4 hover:text-red-900">Remove</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </x-customer-shell>
@endsection
