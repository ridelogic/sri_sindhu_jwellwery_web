@props(['name', 'label', 'theme' => 'light', 'rows' => 5, 'value' => null])

@php
    $id = 'field-'.$name;
    $error = $errors->first($name);
    $dark = $theme === 'dark';
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" data-form-label @class(['block text-[0.66rem] font-semibold tracking-[0.24em] uppercase', 'text-stone' => $dark, 'text-taupe' => ! $dark])>
        {{ $label }}
        @if ($attributes->has('required'))<span class="text-champagne" aria-hidden="true">*</span>@endif
    </label>

    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
        {{ $attributes->except('class')->class([
            'mt-3 block w-full resize-y border bg-transparent px-4 py-3.5 text-base transition-colors duration-300 outline-none focus:border-champagne',
            'border-ivory/20 text-ivory placeholder:text-ivory/30' => $dark,
            'border-obsidian/20 text-obsidian placeholder:text-obsidian/35' => ! $dark,
            'border-red-400' => $error,
        ]) }}
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>{{ old($name, $value) }}</textarea>

    @if ($error)
        <p id="{{ $id }}-error" @class(['mt-2 text-sm', 'text-red-300' => $dark, 'text-red-700' => ! $dark])>{{ $error }}</p>
    @endif
</div>
