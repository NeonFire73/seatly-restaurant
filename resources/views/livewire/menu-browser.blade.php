<div x-data="{ active: null }">
    {{-- Пошук і фільтри --}}
    <div class="container-site">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
            <div class="relative lg:w-80">
                <label for="menu-search" class="sr-only">{{ __('site.menu.search') }}</label>
                <x-site.icon name="search" class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-ink-soft" />
                <input
                    id="menu-search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('site.menu.search_placeholder') }}"
                    maxlength="60"
                    autocomplete="off"
                    class="h-12 w-full rounded-full border border-line bg-surface pl-12 pr-4 text-base placeholder:text-ink-soft focus:border-ink focus:outline-none"
                >
            </div>

            <fieldset class="flex flex-wrap items-center gap-2">
                <legend class="sr-only">{{ __('site.menu.filters') }}</legend>
                @foreach ($tagOptions as $tag)
                    <label class="cursor-pointer">
                        <input type="checkbox" wire:model.live="tags" value="{{ $tag->value }}" class="peer sr-only">
                        <span class="inline-flex min-h-11 items-center rounded-full border border-line px-4 text-sm font-medium transition-colors peer-checked:border-ink peer-checked:bg-ink peer-checked:text-surface peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand hover:border-ink">
                            {{ __('site.tags.'.$tag->value) }}
                        </span>
                    </label>
                @endforeach

                {{-- На телефоні панель розкривається в потоці на всю ширину, щоб не вилізти за правий край екрана --}}
                {{-- wire:ignore.self: інакше після кожної галочки Livewire морфить <details> без атрибута open і панель закривається --}}
                <details wire:ignore.self class="relative open:basis-full sm:open:basis-auto" x-data @click.outside="$el.removeAttribute('open')">
                    <summary class="inline-flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-full border border-line px-4 text-sm font-medium hover:border-ink [&::-webkit-details-marker]:hidden">
                        <x-site.icon name="filter" class="size-4" />
                        {{ __('site.menu.without') }}
                        @if (count($without))
                            <x-badge tone="neutral">{{ count($without) }}</x-badge>
                        @endif
                    </summary>
                    <div class="z-30 mt-2 rounded-card bg-surface p-4 shadow-lg ring-1 ring-line sm:absolute sm:left-0 sm:w-72">
                        <p class="mb-3 text-sm text-ink-soft">{{ __('site.menu.without_hint') }}</p>
                        <div class="grid grid-cols-2 gap-x-3 gap-y-1">
                            @foreach ($allergenOptions as $allergen)
                                <label class="flex min-h-10 cursor-pointer items-center gap-2 text-sm">
                                    <input type="checkbox" wire:model.live="without" value="{{ $allergen->value }}" class="size-4 accent-ink">
                                    {{ \Illuminate\Support\Str::ucfirst(__('site.allergens.'.$allergen->value)) }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </details>

                @if ($this->hasFilters())
                    <x-button variant="text" wire:click="resetFilters">{{ __('site.menu.reset') }}</x-button>
                @endif
            </fieldset>
        </div>
    </div>

    @if ($this->categories->isEmpty())
        <p class="container-site py-16 text-center text-ink-soft" role="status">{{ __('site.menu.nothing_found') }}</p>
    @else
        {{-- Стрічка категорій прилипає під шапкою; активна підсвічується під час прокрутки --}}
        <nav aria-label="{{ __('site.menu.categories') }}" class="sticky top-16 z-30 mt-6 border-y border-line bg-surface/95 backdrop-blur">
            <ul class="container-site flex gap-1 overflow-x-auto py-2 [scrollbar-width:none]">
                @foreach ($this->categories as $category)
                    <li class="shrink-0">
                        <a
                            href="#cat-{{ $category->slug }}"
                            :aria-current="active === '{{ $category->slug }}' ? 'true' : null"
                            class="inline-flex min-h-11 items-center rounded-full px-4 text-sm font-medium transition-colors hover:bg-muted aria-[current=true]:bg-ink aria-[current=true]:text-surface"
                        >{{ $category->name }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="container-site">
            @foreach ($this->categories as $category)
                <section
                    id="cat-{{ $category->slug }}"
                    wire:key="cat-{{ $category->id }}"
                    x-intersect:enter.margin.-35%.0px.-60%.0px="active = '{{ $category->slug }}'"
                    class="pt-10"
                    aria-labelledby="cat-title-{{ $category->id }}"
                >
                    <h2 id="cat-title-{{ $category->id }}" class="text-2xl font-bold tracking-tight">{{ $category->name }}</h2>
                    <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($category->dishes as $dish)
                            <x-dish-card :dish="$dish" interactive wire:key="dish-{{ $dish->id }}" />
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    {{-- Деталі страви: нативний dialog сам утримує фокус і закривається клавішею Esc.
         Кнопка й клік по тлу самі повідомляють компонент: подія close у фоновій вкладці може запізнитися. --}}
    <dialog
        x-data="{ shut() { this.$root.close(); if (this.$wire.openDishId !== null) this.$wire.closeDish(); } }"
        x-on:dish-opened.window="$nextTick(() => $el.open || $el.showModal())"
        @close="$wire.openDishId !== null && $wire.closeDish()"
        @click.self="shut()"
        aria-labelledby="dish-dialog-title"
        class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-card bg-surface p-0 text-ink backdrop:bg-ink/50"
    >
        @if ($dish = $this->openDish)
            <div wire:key="dialog-{{ $dish->id }}">
                @if ($dish->image)
                    <x-picture :path="$dish->image" :alt="$dish->name" sizes="(min-width: 640px) 512px, 100vw" class="aspect-[4/3] w-full object-cover" width="960" height="720" />
                @endif
                <div class="space-y-4 p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-ink-soft">{{ $dish->category?->name }}</p>
                            <h2 id="dish-dialog-title" class="text-2xl font-bold tracking-tight">{{ $dish->name }}</h2>
                        </div>
                        <button type="button" @click="shut()" class="-mr-2 -mt-2 inline-flex size-11 shrink-0 items-center justify-center rounded-full hover:bg-muted">
                            <x-site.icon name="x" />
                            <span class="sr-only">{{ __('site.menu.close') }}</span>
                        </button>
                    </div>

                    @if (! $dish->is_available)
                        <x-badge>{{ __('site.menu.unavailable') }}</x-badge>
                    @endif

                    @if ($dish->description)
                        <div>
                            <h3 class="text-sm font-semibold">{{ __('site.menu.composition') }}</h3>
                            <p class="mt-1 text-ink-soft">{{ $dish->description }}</p>
                        </div>
                    @endif

                    <div>
                        <h3 class="text-sm font-semibold">{{ __('site.menu.allergens') }}</h3>
                        <p class="mt-1 text-ink-soft">
                            @if ($dish->allergens->isEmpty())
                                {{ __('site.menu.no_allergens') }}
                            @else
                                {{ \Illuminate\Support\Str::ucfirst($dish->allergens->map(fn ($a) => __('site.allergens.'.$a->value))->implode(', ')) }}
                            @endif
                        </p>
                    </div>

                    <dl class="flex flex-wrap gap-x-8 gap-y-2 border-t border-line pt-4 text-sm">
                        @if ($dish->weight && $dish->unit)
                            <div><dt class="sr-only">{{ __('site.units.'.$dish->unit->value) }}</dt><dd>{{ $dish->weight }} {{ __('site.units.'.$dish->unit->value) }}</dd></div>
                        @endif
                        <div class="flex gap-1"><dt class="text-ink-soft">{{ __('site.menu.cook_time') }}:</dt><dd>{{ __('site.menu.minutes', ['count' => $dish->cook_time_minutes]) }}</dd></div>
                        <div class="ml-auto text-lg font-semibold"><dd>{{ \App\Support\Money::format($dish->price) }}</dd></div>
                    </dl>

                    @if ($dish->tags->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($dish->tags as $tag)
                                <x-badge tone="outline">{{ __('site.tags.'.$tag->value) }}</x-badge>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </dialog>
</div>
