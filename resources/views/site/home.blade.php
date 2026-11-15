@php
    use App\Services\SiteContent;

    $hero = SiteContent::block('home.hero');
    $about = SiteContent::block('home.about');
    $how = SiteContent::block('home.how_it_works');
    $steps = [
        ['block' => SiteContent::block('home.step_book'), 'icon' => 'calendar'],
        ['block' => SiteContent::block('home.step_preorder'), 'icon' => 'utensils'],
        ['block' => SiteContent::block('home.step_pay'), 'icon' => 'qr'],
    ];
    $gallery = SiteContent::gallery();
    $contacts = SiteContent::contacts();
@endphp

<x-layouts::app seo="home" :schema="\App\Support\StructuredData::restaurant()">
    {{-- 1. Перший екран --}}
    @if ($hero)
        <section class="relative overflow-hidden">
            <div @class(['container-site grid items-center gap-10 py-16 sm:py-24', 'lg:grid-cols-2' => $hero->image])>
                <div class="max-w-2xl">
                    <h1 class="text-4xl font-bold leading-[1.1] tracking-tight text-balance sm:text-6xl">{{ $hero->title }}</h1>
                    @if ($hero->subtitle)
                        <p class="mt-6 text-lg text-ink-soft text-pretty">{{ $hero->subtitle }}</p>
                    @endif
                    <div class="mt-8 flex flex-wrap gap-3">
                        <x-button size="lg" :href="$hero->button_url ?: locale_route('booking')">
                            {{ $hero->button_label ?: __('site.nav.book') }}
                        </x-button>
                        <x-button size="lg" variant="secondary" :href="locale_route('menu')">
                            {{ __('site.home.view_menu') }}
                        </x-button>
                    </div>
                </div>

                @if ($hero->image)
                    <x-picture :path="$hero->image" :alt="''" priority sizes="(min-width: 1024px) 50vw, 100vw" class="aspect-[4/3] w-full rounded-card object-cover" width="960" height="720" />
                @endif
            </div>
        </section>
    @else
        <h1 class="sr-only">{{ config('seatly.brand') }}</h1>
    @endif

    {{-- 2. Коротко про ресторан --}}
    @if ($about)
        <section class="bg-muted py-16 sm:py-20" aria-labelledby="about-title">
            <div @class(['container-site grid items-center gap-10', 'lg:grid-cols-2' => $about->image])>
                @if ($about->image)
                    <x-picture :path="$about->image" :alt="''" sizes="(min-width: 1024px) 50vw, 100vw" class="aspect-[4/3] w-full rounded-card object-cover" width="960" height="720" />
                @endif
                <div class="max-w-2xl">
                    <h2 id="about-title" class="text-3xl font-bold tracking-tight">{{ $about->title }}</h2>
                    <div class="mt-4 space-y-4 text-lg text-ink-soft">
                        <x-site.paragraphs :text="$about->body" />
                    </div>
                    @if ($about->button_label)
                        <x-button variant="text" class="mt-4" :href="$about->button_url ?: locale_route('about')">
                            {{ $about->button_label }}
                            <x-site.icon name="arrow-right" class="size-4" />
                        </x-button>
                    @endif
                </div>
            </div>
        </section>
    @endif

    {{-- 3. Популярні страви --}}
    @if ($popular->isNotEmpty())
        <section class="py-16 sm:py-20" aria-labelledby="popular-title">
            <div class="container-site">
                <div class="flex items-end justify-between gap-4">
                    <h2 id="popular-title" class="text-3xl font-bold tracking-tight">{{ __('site.home.popular') }}</h2>
                    <x-button variant="text" :href="locale_route('menu')" class="shrink-0 whitespace-nowrap">
                        {{ __('site.home.popular_all') }}
                        <x-site.icon name="arrow-right" class="size-4" />
                    </x-button>
                </div>
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($popular as $dish)
                        <x-dish-card :dish="$dish" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 4. Як це працює --}}
    @if ($how)
        <section class="bg-muted py-16 sm:py-20" aria-labelledby="how-title">
            <div class="container-site">
                <h2 id="how-title" class="text-3xl font-bold tracking-tight">{{ $how->title }}</h2>
                @if ($how->subtitle)
                    <p class="mt-3 text-lg text-ink-soft">{{ $how->subtitle }}</p>
                @endif
                <ol class="mt-10 grid gap-6 md:grid-cols-3">
                    @foreach ($steps as $index => $step)
                        @if ($step['block'])
                            <li class="rounded-card bg-surface p-6">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-11 items-center justify-center rounded-full border border-ink">
                                        <x-site.icon :name="$step['icon']" />
                                    </span>
                                    <span class="text-sm font-semibold text-ink-soft">{{ $index + 1 }}</span>
                                </div>
                                <h3 class="mt-5 text-xl font-semibold">{{ $step['block']->title }}</h3>
                                <p class="mt-2 text-ink-soft">{{ $step['block']->body }}</p>
                            </li>
                        @endif
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    {{-- 5. Галерея --}}
    @if ($gallery->isNotEmpty())
        <section class="py-16 sm:py-20" aria-labelledby="gallery-title">
            <div class="container-site">
                <h2 id="gallery-title" class="text-3xl font-bold tracking-tight">{{ __('site.home.gallery') }}</h2>
                <ul class="mt-8 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
                    @foreach ($gallery as $photo)
                        <li>
                            <x-picture :path="$photo->image" :alt="$photo->alt" sizes="(min-width: 1024px) 33vw, 50vw" class="aspect-square w-full rounded-card object-cover" width="480" height="480" />
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- 6. Години роботи, адреса, мапа --}}
    <section class="py-16 sm:py-20" aria-labelledby="visit-title">
        <div class="container-site grid gap-10 lg:grid-cols-[1fr_1.4fr]">
            <div>
                <h2 id="visit-title" class="text-3xl font-bold tracking-tight">{{ __('site.home.visit') }}</h2>
                <div class="mt-6 space-y-3">
                    @if (SiteContent::address())
                        <p class="flex gap-3"><x-site.icon name="map-pin" class="size-5 shrink-0" />{{ SiteContent::address() }}</p>
                    @endif
                    @if ($contacts['phone'])
                        <p class="flex gap-3"><x-site.icon name="phone" class="size-5 shrink-0" /><a href="tel:{{ preg_replace('/[^\d+]/', '', $contacts['phone']) }}" class="hover:underline">{{ $contacts['phone'] }}</a></p>
                    @endif
                </div>
                <h3 class="mt-8 flex items-center gap-3 font-semibold"><x-site.icon name="clock" class="size-5" />{{ __('site.contacts.hours') }}</h3>
                <x-site.hours class="mt-2" />
            </div>
            <div>
                <x-site.map />
            </div>
        </div>
    </section>
</x-layouts::app>
