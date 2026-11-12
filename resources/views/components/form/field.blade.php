@props([
    'label',
    // id поля всередині: з ним пов'язуються підпис, підказка й помилка
    'for',
    // Назва властивості Livewire, чию помилку показувати
    'error' => null,
    'hint' => null,
    'optional' => false,
])

@php
    $message = null;

    if ($error !== null) {
        $message = $errors->first($error);
    }
@endphp

{{-- Поле форми з підписом, підказкою й помилкою. Саме поле (x-form.input чи інше) передається в слот
     і має посилатися на підказку й помилку: aria-describedby="{{ $for }}-hint {{ $for }}-error" --}}
<div {{ $attributes->class('space-y-1.5') }}>
    <label for="{{ $for }}" class="flex items-baseline justify-between gap-3 text-sm font-medium">
        <span>{{ $label }}</span>
        @if ($optional)
            <span class="text-xs font-normal text-ink-soft">{{ __('booking.contacts.optional') }}</span>
        @endif
    </label>

    {{ $slot }}

    @if ($hint)
        <p id="{{ $for }}-hint" class="text-sm text-ink-soft">{{ $hint }}</p>
    @endif

    {{-- Контейнер помилки є завжди: role="alert" оголошує текст, коли він з'являється --}}
    <p id="{{ $for }}-error" role="alert" class="text-sm font-medium text-danger">{{ $message }}</p>
</div>
