@props(['name', 'label', 'options' => [], 'selected' => null, 'placeholder' => 'Please choose', 'theme' => 'light'])

@php
    $id = 'field-'.$name;
    $error = $errors->first($name);
    $dark = $theme === 'dark';
    $current = old($name, $selected);
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" data-form-label @class(['block text-[0.66rem] font-semibold tracking-[0.24em] uppercase', 'text-stone' => $dark, 'text-taupe' => ! $dark])>
        {{ $label }}
        @if ($attributes->has('required'))<span class="text-champagne" aria-hidden="true">*</span>@endif
    </label>

    <div class="relative mt-3">
        <select id="{{ $id }}" name="{{ $name }}"
            {{ $attributes->except('class')->class([
                'block w-full appearance-none border bg-transparent py-3.5 pr-12 pl-4 text-base transition-colors duration-300 outline-none focus:border-champagne',
                'border-ivory/20 text-ivory [&>option]:text-obsidian' => $dark,
                'border-obsidian/20 text-obsidian' => ! $dark,
                'border-red-400' => $error,
            ]) }}
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
            <option value="" disabled @selected(blank($current))>{{ $placeholder }}</option>
            @foreach ($options as $value => $optionLabel)
                <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $optionLabel }}</option>
            @endforeach
        </select>
        <x-icon name="arrow-right" class="pointer-events-none absolute top-1/2 right-4 size-4 -translate-y-1/2 rotate-90 text-gold-deep" />
    </div>

    @if ($error)
        <p id="{{ $id }}-error" @class(['mt-2 text-sm', 'text-red-300' => $dark, 'text-red-700' => ! $dark])>{{ $error }}</p>
    @endif
</div>
