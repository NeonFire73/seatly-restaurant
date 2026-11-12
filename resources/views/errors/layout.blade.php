@php
    // Сторінка помилки не звертається до бази: вона має відкритися, навіть коли база недоступна
    $code = (string) $code;
    $homeUrl = url(app()->getLocale() === \App\Support\Localization::main() ? '/' : app()->getLocale());
@endphp

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ __('site.errors.'.$code.'.title') }} — {{ config('seatly.brand') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-screen flex-col bg-surface font-sans text-ink antialiased">
        <header class="border-b border-line">
            <div class="container-site flex h-16 items-center">
                <a href="{{ $homeUrl }}" class="flex items-baseline gap-1.5">
                    <span class="text-xl font-bold tracking-tight">Seatly</span>
                    <span class="text-[0.65rem] font-medium uppercase tracking-[0.25em] text-ink-soft">Restaurant</span>
                </a>
            </div>
        </header>

        <main class="container-site flex flex-1 flex-col items-start justify-center py-20">
            <p class="text-sm font-semibold text-ink-soft">{{ $code }}</p>
            <h1 class="mt-2 text-4xl font-bold tracking-tight sm:text-5xl">{{ __('site.errors.'.$code.'.title') }}</h1>
            <p class="mt-4 text-lg text-ink-soft">{{ __('site.errors.'.$code.'.text') }}</p>
            <x-button size="lg" class="mt-8" :href="$homeUrl">{{ __('site.errors.home') }}</x-button>
        </main>

        <footer class="border-t border-line">
            <p class="container-site py-6 text-xs text-ink-soft">{{ __('site.demo_notice') }}</p>
        </footer>
    </body>
</html>
