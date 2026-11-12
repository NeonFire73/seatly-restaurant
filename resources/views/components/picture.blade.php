@props([
    'path',
    'alt' => '',
    'sizes' => '100vw',
    'priority' => false,
])

@php
    $variants = \App\Support\Images::variants($path);
    $src = \App\Support\Images::url($path);

    // Якщо WebP-копій ще немає (наприклад, фото завантажили вручну в storage), показується оригінал
    if ($variants !== []) {
        $src = end($variants);
    }

    $srcset = collect($variants)->map(fn (string $url, int $width) => "{$url} {$width}w")->implode(', ');
@endphp

@if ($src)
    <img
        src="{{ $src }}"
        @if ($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif
        alt="{{ $alt }}"
        @if ($priority) fetchpriority="high" @else loading="lazy" @endif
        decoding="async"
        {{ $attributes }}
    >
@endif
