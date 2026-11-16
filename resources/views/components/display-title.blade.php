@props(['text', 'emphasis' => null, 'emphasisClass' => 'text-gold-bright/95'])

{{-- Renders a headline, setting the optional emphasis phrase in italic. Both values are escaped. --}}
@php
    $html = e($text);
    if ($emphasis && str_contains($text, $emphasis)) {
        $html = str_replace(e($emphasis), '<em class="'.e($emphasisClass).'">'.e($emphasis).'</em>', $html);
    }
@endphp
{!! $html !!}
