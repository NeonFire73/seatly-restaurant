@php
    $links = [
        'menu' => __('site.nav.menu'),
        'about' => __('site.nav.about'),
        'contacts' => __('site.nav.contacts'),
    ];
    $currentRoute = \App\Support\Localization::stripLocalePrefix((string) request()->route()?->getName());
@endphp

{{-- Шапка закріплена, кнопка «Забронювати» видима завжди, зокрема на телефоні (частина 1, п. 5.1) --}}
<header x-data="{ open: false }" @keydown.escape.window="open = false" class="sticky top-0 z-40 border-b border-line bg-surface/95 backdrop-blur">
    <div class="container-site flex h-16 items-center gap-4">
        <x-site.logo />

        <nav aria-label="{{ __('site.nav.main') }}" class="ml-6 hidden md:block">
            <ul class="flex items-center gap-1">
                @foreach ($links as $route => $label)
                    <li>
                        <a
                            href="{{ locale_route($route) }}"
                            @if ($currentRoute === $route) aria-current="page" @endif
                            class="inline-flex min-h-11 items-center rounded-full px-3 text-sm font-medium transition-colors hover:bg-muted aria-[current=page]:bg-muted"
                        >{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="ml-auto flex items-center gap-2">
            <x-site.lang-switcher class="hidden sm:flex" />

            <x-button :href="locale_route('booking')" class="px-4 sm:px-5">
                <x-site.icon name="calendar" class="size-4" />
                {{ __('site.nav.book') }}
            </x-button>

            <button
                type="button"
                class="inline-flex size-11 items-center justify-center rounded-full hover:bg-muted md:hidden"
                @click="open = ! open"
                :aria-expanded="open.toString()"
                aria-controls="mobile-nav"
            >
                <span class="sr-only" x-text="open ? @js(__('site.nav.close_menu')) : @js(__('site.nav.open_menu'))">{{ __('site.nav.open_menu') }}</span>
                <x-site.icon name="menu" x-show="! open" />
                <x-site.icon name="x" x-show="open" x-cloak />
            </button>
        </div>
    </div>

    <nav id="mobile-nav" x-show="open" x-cloak x-transition.opacity.duration.150ms aria-label="{{ __('site.nav.main') }}" class="border-t border-line bg-surface md:hidden">
        <ul class="container-site flex flex-col py-2">
            @foreach ($links as $route => $label)
                <li>
                    <a href="{{ locale_route($route) }}" @if ($currentRoute === $route) aria-current="page" @endif class="flex min-h-12 items-center text-lg font-medium aria-[current=page]:text-brand">{{ $label }}</a>
                </li>
            @endforeach
            <li class="border-t border-line pt-3 pb-2">
                <x-site.lang-switcher />
            </li>
        </ul>
    </nav>
</header>
