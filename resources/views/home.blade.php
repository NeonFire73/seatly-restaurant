<x-layouts::app>
    <main class="flex min-h-screen flex-col items-center justify-center gap-6 px-4 text-center">
        <p class="text-sm uppercase tracking-[0.3em] text-ink/70">{{ __('site.under_construction') }}</p>

        <h1 class="text-5xl font-bold tracking-tight sm:text-7xl">{{ config('app.name') }}</h1>

        <p class="max-w-xl text-lg text-ink/80">{{ __('site.tagline') }}</p>

        <a
            href="{{ url('/admin') }}"
            class="rounded-full bg-brand px-6 py-3 font-semibold text-on-brand transition hover:bg-brand/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
            {{ __('site.admin_link') }}
        </a>

        <p class="mt-8 text-sm text-ink/70">{{ __('site.demo_notice') }}</p>
    </main>
</x-layouts::app>
