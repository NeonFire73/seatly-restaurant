@props([
    // Категорії зі стравами, дозволеними для попереднього замовлення
    'categories',
    // Кошик: ідентифікатор страви => кількість
    'cart' => [],
])

@php
    use App\Support\Money;
@endphp

{{-- Меню в режимі кошика (частина 1, п. 5.3). Компонент-господар має методи addDish і removeDish (трейт HasPreorderCart) --}}
<div {{ $attributes->class('space-y-6') }}>
    @forelse ($categories as $category)
        <section aria-labelledby="preorder-category-{{ $category->id }}">
            <h3 id="preorder-category-{{ $category->id }}" class="text-sm font-semibold uppercase tracking-wide text-ink-soft">{{ $category->name }}</h3>

            <ul class="mt-2 divide-y divide-line rounded-card ring-1 ring-line">
                @foreach ($category->dishes as $dish)
                    @php
                        $quantity = (int) ($cart[$dish->id] ?? 0);
                    @endphp
                    <li wire:key="preorder-dish-{{ $dish->id }}" class="flex items-center gap-3 p-3 sm:p-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium leading-snug">{{ $dish->name }}</p>
                            <p class="mt-0.5 text-sm text-ink-soft">
                                {{ Money::format($dish->price) }}
                                @if ($dish->weight && $dish->unit)
                                    · {{ $dish->weight }} {{ __('site.units.'.$dish->unit->value) }}
                                @endif
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            @if ($quantity > 0)
                                <button type="button" wire:click="removeDish({{ $dish->id }})" class="inline-flex size-11 items-center justify-center rounded-full border border-line transition-colors hover:border-ink">
                                    <x-site.icon name="minus" class="size-4" />
                                    <span class="sr-only">{{ __('booking.preorder.remove', ['dish' => $dish->name]) }}</span>
                                </button>
                                <output class="w-7 text-center font-semibold tabular-nums" aria-live="polite">{{ $quantity }}</output>
                            @endif
                            <button
                                type="button"
                                wire:click="addDish({{ $dish->id }})"
                                @class([
                                    'inline-flex size-11 items-center justify-center rounded-full border transition-colors',
                                    'border-brand bg-brand text-on-brand hover:bg-brand-hover' => $quantity > 0,
                                    'border-ink hover:bg-ink hover:text-surface' => $quantity === 0,
                                ])
                            >
                                <x-site.icon name="plus" class="size-4" />
                                <span class="sr-only">{{ __('booking.preorder.add', ['dish' => $dish->name]) }}</span>
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="text-sm text-ink-soft">{{ __('booking.preorder.menu_empty') }}</p>
    @endforelse
</div>
