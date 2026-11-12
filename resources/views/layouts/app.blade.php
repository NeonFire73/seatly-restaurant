@props([
    // Ключ запису SEO з адмінки (home, menu, …); для сторінок без нього задаються title/description
    'seo' => null,
    'title' => null,
    'description' => null,
    // Сторінки з персональними даними (бронювання гостя) закриті від індексації
    'noindex' => false,
])

@php
    use App\Services\SiteContent;
    use App\Support\Localization;

    $entry = $seo ? SiteContent::seo($seo) : null;
    $pageTitle = $title ?? $entry?->title ?? config('seatly.brand');

    if ($title && ! str_contains($title, config('seatly.brand'))) {
        $pageTitle = $title.' — '.config('seatly.brand');
    }

    $pageDescription = $description ?? $entry?->description ?? __('site.tagline');
    $ogImage = $entry?->og_image ? \App\Support\Images::url($entry->og_image) : null;
@endphp

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ $pageDescription }}">
        @if ($noindex)
            <meta name="robots" content="noindex, nofollow">
        @endif
        <link rel="canonical" href="{{ Localization::currentUrlFor(app()->getLocale()) }}">

        @foreach (Localization::locales() as $locale)
            <link rel="alternate" hreflang="{{ $locale }}" href="{{ Localization::currentUrlFor($locale) }}">
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ Localization::currentUrlFor(Localization::main()) }}">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('seatly.brand') }}">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:locale" content="{{ app()->getLocale() === 'uk' ? 'uk_UA' : 'en_US' }}">
        @if ($ogImage)
            <meta property="og:image" content="{{ $ogImage }}">
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="flex min-h-screen flex-col bg-surface font-sans text-ink antialiased">
        <a href="#content" class="sr-only z-50 rounded-full bg-ink px-4 py-2 text-surface focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
            {{ __('site.skip_to_content') }}
        </a>

        <x-site.header />

        <main id="content" class="flex-1">
            {{ $slot }}
        </main>

        <x-site.footer />

        @livewireScripts
    </body>
</html>
