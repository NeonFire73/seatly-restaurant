@props(['number'])

{{-- Полегшений макет сторінки столика: без навігації сайту й підвалу, лише логотип, номер столика й мова.
     Шапка лежить поза Livewire-компонентом: перемикач мови будує адреси з поточного маршруту,
     а під час оновлення компонента поточним був би службовий маршрут Livewire. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        <title>{{ __('table.title', ['number' => $number]) }} — {{ config('seatly.brand') }}</title>
        <meta name="robots" content="noindex, nofollow">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="flex min-h-screen flex-col bg-surface font-sans text-ink antialiased">
        <a href="#content" class="sr-only z-50 rounded-full bg-ink px-4 py-2 text-surface focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
            {{ __('site.skip_to_content') }}
        </a>

        <header class="sticky top-0 z-40 border-b border-line bg-surface/95 backdrop-blur">
            <div class="container-site flex h-14 max-w-2xl items-center gap-3">
                <x-site.logo />
                <h1 class="ml-auto text-base font-semibold">{{ __('table.title', ['number' => $number]) }}</h1>
                <x-site.lang-switcher />
            </div>
        </header>

        <main id="content" class="flex-1">
            {{ $slot }}
        </main>

        <x-toast />

        @livewireScripts
    </body>
</html>
