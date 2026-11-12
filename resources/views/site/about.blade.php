@php
    use App\Services\SiteContent;

    $story = SiteContent::block('about.story');
    $kitchen = SiteContent::block('about.kitchen');
@endphp

<x-layouts::app seo="about">
    <div class="container-site pt-12">
        <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">{{ $story?->title ?? __('site.nav.about') }}</h1>
        @if ($story?->subtitle)
            <p class="mt-4 max-w-2xl text-xl text-ink-soft">{{ $story->subtitle }}</p>
        @endif
    </div>

    @foreach ([$story, $kitchen] as $index => $block)
        @if ($block)
            <section class="py-12" @if ($index > 0) aria-labelledby="about-{{ $index }}" @endif>
                <div @class(['container-site grid items-center gap-10', 'lg:grid-cols-2' => $block->image])>
                    @if ($block->image)
                        <x-picture :path="$block->image" :alt="''" :priority="$index === 0" sizes="(min-width: 1024px) 50vw, 100vw" @class(['aspect-[4/3] w-full rounded-card object-cover', 'lg:order-last' => $index % 2 === 1]) width="960" height="720" />
                    @endif
                    <div class="max-w-2xl">
                        @if ($index > 0)
                            <h2 id="about-{{ $index }}" class="mb-4 text-3xl font-bold tracking-tight">{{ $block->title }}</h2>
                        @endif
                        <div class="space-y-4 text-lg leading-relaxed text-ink-soft">
                            <x-site.paragraphs :text="$block->body" />
                        </div>
                    </div>
                </div>
            </section>
        @endif
    @endforeach

    <div class="container-site">
        <x-button size="lg" :href="locale_route('booking')">{{ __('site.nav.book') }}</x-button>
    </div>
</x-layouts::app>
