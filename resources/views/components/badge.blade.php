@props(['tone' => 'neutral'])

@php
    $tones = [
        'neutral' => 'bg-muted text-ink',
        'outline' => 'border border-line text-ink-soft',
        'brand' => 'bg-brand text-on-brand',
    ];
@endphp

<span {{ $attributes->class('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.$tones[$tone]) }}>{{ $slot }}</span>
