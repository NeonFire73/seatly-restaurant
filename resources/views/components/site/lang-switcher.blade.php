@php
    use App\Support\Localization;
@endphp

<ul {{ $attributes->class('flex items-center gap-1') }} aria-label="{{ __('site.nav.language') }}">
    @foreach (config('seatly.locales') as $locale => $name)
        <li>
            <a
                href="{{ Localization::currentUrlFor($locale) }}"
                hreflang="{{ $locale }}"
                lang="{{ $locale }}"
                title="{{ $name }}"
                @if (app()->getLocale() === $locale) aria-current="true" @endif
                class="inline-flex size-11 items-center justify-center rounded-full text-xs font-semibold uppercase text-ink-soft transition-colors hover:bg-muted hover:text-ink aria-[current=true]:text-ink aria-[current=true]:underline aria-[current=true]:underline-offset-4"
            >
                <span class="sr-only">{{ $name }}</span>
                <span aria-hidden="true">{{ $locale === 'uk' ? 'UA' : strtoupper($locale) }}</span>
            </a>
        </li>
    @endforeach
</ul>
