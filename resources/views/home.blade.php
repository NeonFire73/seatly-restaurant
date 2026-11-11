<x-layouts::app>
    <main class="flex min-h-screen flex-col items-center justify-center gap-6 px-4 text-center">
        <p class="text-sm uppercase tracking-[0.3em] text-accent">{{ __('site.under_construction') }}</p>

        <h1 class="font-serif text-5xl sm:text-7xl">{{ config('app.name') }}</h1>

        <p class="max-w-xl text-lg text-cream/80">{{ __('site.tagline') }}</p>

        <a
            href="{{ url('/admin') }}"
            class="rounded-full bg-accent px-6 py-3 font-medium text-ink transition hover:bg-accent/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
        >
            {{ __('site.admin_link') }}
        </a>

        <p class="mt-8 text-sm text-cream/60">{{ __('site.demo_notice') }}</p>
    </main>
</x-layouts::app>
