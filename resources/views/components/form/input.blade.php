@props([
    'id',
    'type' => 'text',
    'error' => null,
    'multiline' => false,
])

@php
    $invalid = $error !== null && $errors->has($error);
    $classes = 'w-full rounded-xl border bg-surface px-4 text-base placeholder:text-ink-soft focus:border-ink focus:outline-none';

    if ($invalid) {
        $classes .= ' border-danger';
    } else {
        $classes .= ' border-line';
    }
@endphp

@if ($multiline)
    <textarea
        id="{{ $id }}"
        aria-describedby="{{ $id }}-hint {{ $id }}-error"
        @if ($invalid) aria-invalid="true" @endif
        {{ $attributes->class($classes.' min-h-24 py-3') }}
    ></textarea>
@else
    <input
        id="{{ $id }}"
        type="{{ $type }}"
        aria-describedby="{{ $id }}-hint {{ $id }}-error"
        @if ($invalid) aria-invalid="true" @endif
        {{ $attributes->class($classes.' h-12') }}
    >
@endif
