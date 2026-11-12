@props([
    'dish',
    // Картка з кнопкою відкриває деталі (Livewire-метод showDish); без неї — просто показ (головна)
    'interactive' => false,
])

@php
    /** @var \App\Models\Dish $dish */
    $available = $dish->is_available;
@endphp

<article {{ $attributes->class(['group relative flex flex-col overflow-hidden rounded-card bg-surface ring-1 ring-line', 'opacity-60' => ! $available]) }}>
    <div class="relative aspect-[4/3] bg-muted">
        @if ($dish->image)
            <x-picture :path="$dish->image" :alt="$dish->name" sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw" class="size-full object-cover" width="480" height="360" />
        @else
            <div class="flex size-full flex-col items-center justify-center gap-2 text-ink-soft">
                <x-site.icon name="plate" class="size-10" />
                <span class="text-xs">{{ __('site.menu.photo_soon') }}</span>
            </div>
        @endif

        @if (! $available)
            <x-badge tone="neutral" class="absolute left-3 top-3">{{ __('site.menu.unavailable') }}</x-badge>
        @elseif ($dish->is_popular)
            <x-badge tone="brand" class="absolute left-3 top-3">{{ __('site.menu.popular') }}</x-badge>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-2 p-4">
        <div class="flex items-start justify-between gap-3">
            <h3 class="font-semibold leading-snug">
                @if ($interactive)
                    {{-- Розтягнута на всю картку кнопка: зона натискання велика, а в розмітці одна інтерактивна точка --}}
                    <button type="button" wire:click="showDish({{ $dish->id }})" class="text-left after:absolute after:inset-0 after:content-['']" aria-label="{{ __('site.menu.details') }}: {{ $dish->name }}">
                        {{ $dish->name }}
                    </button>
                @else
                    {{ $dish->name }}
                @endif
            </h3>
            <p class="shrink-0 font-semibold">{{ \App\Support\Money::format($dish->price) }}</p>
        </div>

        @if ($dish->description)
            <p class="line-clamp-2 text-sm text-ink-soft">{{ $dish->description }}</p>
        @endif

        <div class="mt-auto flex flex-wrap items-center gap-1.5 pt-1">
            @if ($dish->weight && $dish->unit)
                <span class="mr-1 text-xs text-ink-soft">{{ $dish->weight }} {{ __('site.units.'.$dish->unit->value) }}</span>
            @endif
            @foreach ($dish->tags as $tag)
                <x-badge tone="outline">{{ __('site.tags.'.$tag->value) }}</x-badge>
            @endforeach
        </div>
    </div>
</article>
