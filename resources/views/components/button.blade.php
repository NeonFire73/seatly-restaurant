@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'size' => 'md',
])

@php
    // Фірмовий колір — лише в основної кнопки; решта чорно-білі (частина 1, п. 2.2)
    $variants = [
        'primary' => 'bg-brand text-on-brand hover:bg-brand-hover',
        'secondary' => 'border border-ink text-ink hover:bg-ink hover:text-surface',
        'text' => 'text-ink underline-offset-4 hover:underline',
    ];

    $sizes = [
        'md' => 'min-h-11 px-5 text-sm',
        'lg' => 'min-h-12 px-6 text-base',
    ];

    // Текстова кнопка без бічних відступів вирівнюється з текстом поруч; висота лишається 44 px для зручного натискання
    if ($variant === 'text') {
        $sizes = [
            'md' => 'min-h-11 text-sm',
            'lg' => 'min-h-12 text-base',
        ];
    }

    $classes = 'inline-flex items-center justify-center gap-2 rounded-full font-semibold transition-colors duration-200 disabled:pointer-events-none disabled:opacity-50 '
        .$variants[$variant].' '.$sizes[$size];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
