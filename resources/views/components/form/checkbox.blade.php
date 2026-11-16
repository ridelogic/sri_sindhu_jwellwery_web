@props(['name', 'theme' => 'light', 'value' => '1'])

@php
    $id = 'field-'.$name;
    $error = $errors->first($name);
    $dark = $theme === 'dark';
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" @class(['flex cursor-pointer items-start gap-3 text-sm leading-relaxed', 'text-ivory/75' => $dark, 'text-taupe' => ! $dark])>
        <input id="{{ $id }}" name="{{ $name }}" type="checkbox" value="{{ $value }}" @checked(old($name))
            {{ $attributes->except('class')->class(['mt-0.5 size-[1.1rem] shrink-0 cursor-pointer accent-champagne']) }}
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
        <span>{{ $slot }}</span>
    </label>
    @if ($error)
        <p id="{{ $id }}-error" @class(['mt-2 text-sm', 'text-red-300' => $dark, 'text-red-700' => ! $dark])>{{ $error }}</p>
    @endif
</div>
