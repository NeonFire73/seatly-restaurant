@php
    $phone = \App\Services\SiteContent::contacts()['phone'];
@endphp

{{-- Тимчасова сторінка до етапу 4, на якому тут з'явиться майстер бронювання --}}
<x-layouts::app seo="booking">
    <div class="container-site max-w-2xl py-20 text-center">
        <span class="mx-auto flex size-14 items-center justify-center rounded-full border border-ink">
            <x-site.icon name="calendar" class="size-6" />
        </span>
        <h1 class="mt-6 text-4xl font-bold tracking-tight">{{ __('site.booking.title') }}</h1>
        <p class="mt-4 text-lg text-ink-soft">{{ __('site.booking.soon') }}</p>
        @if ($phone)
            <p class="mt-6 text-ink-soft">{{ __('site.booking.call') }}</p>
            <x-button size="lg" class="mt-4" :href="'tel:'.preg_replace('/[^\d+]/', '', $phone)">
                <x-site.icon name="phone" class="size-5" />
                {{ $phone }}
            </x-button>
        @endif
    </div>
</x-layouts::app>
