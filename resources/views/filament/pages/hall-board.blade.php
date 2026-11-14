{{-- Стилі тут, а не класами Tailwind: тема панелі Filament зібрана заздалегідь і довільних класів не містить.
     Кольори станів задано окремо для світлої й темної теми (частина 2, розділ 7); стан завжди підписано текстом. --}}
@php
    use App\Enums\OrderItemStatus;
    use App\Enums\ReservationStatus;
    use App\Filament\Pages\HallBoard;
    use App\Support\LocalTime;
    use App\Support\Money;
@endphp

<x-filament-panels::page>
    <style>
        .hb { --hb-line: #e5e7eb; --hb-soft: #6b7280; --hb-card: #ffffff; --hb-head: #f9fafb; }
        .dark .hb { --hb-line: #3f3f46; --hb-soft: #a1a1aa; --hb-card: #18181b; --hb-head: #27272a; }
        .hb-free { --hb-state: #6b7280; --hb-tint: transparent; }
        .hb-soon { --hb-state: #b45309; --hb-tint: #fef3c7; }
        .hb-busy { --hb-state: #1d4ed8; --hb-tint: #dbeafe; }
        .hb-due { --hb-state: #b91c1c; --hb-tint: #fee2e2; }
        .hb-paid { --hb-state: #15803d; --hb-tint: #dcfce7; }
        .hb-call { --hb-state: #7e22ce; --hb-tint: #f3e8ff; }
        .dark .hb-free { --hb-state: #a1a1aa; }
        .dark .hb-soon { --hb-state: #fbbf24; --hb-tint: #422006; }
        .dark .hb-busy { --hb-state: #93c5fd; --hb-tint: #172554; }
        .dark .hb-due { --hb-state: #fca5a5; --hb-tint: #450a0a; }
        .dark .hb-paid { --hb-state: #86efac; --hb-tint: #052e16; }
        .dark .hb-call { --hb-state: #d8b4fe; --hb-tint: #3b0764; }

        .hb-toolbar { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .hb-layout { display: grid; gap: 1.25rem; align-items: start; }
        @media (min-width: 64rem) { .hb-layout.hb-with-panel { grid-template-columns: minmax(0, 1fr) minmax(22rem, 28rem); } }
        .hb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr)); gap: 0.75rem; }
        .hb-table { text-align: left; border: 1px solid var(--hb-line); border-left: 0.375rem solid var(--hb-state); border-radius: 0.75rem; background: var(--hb-card); background-image: linear-gradient(var(--hb-tint), var(--hb-tint)); padding: 0.75rem; min-height: 6.5rem; display: flex; flex-direction: column; gap: 0.15rem; cursor: pointer; }
        .hb-table:hover, .hb-table:focus-visible { outline: 2px solid var(--hb-state); outline-offset: 1px; }
        .hb-table[aria-pressed="true"] { outline: 3px solid var(--hb-state); outline-offset: 1px; }
        .hb-table b { font-size: 1.375rem; line-height: 1.1; }
        .hb-table small { color: var(--hb-soft); }
        .hb-state { color: var(--hb-state); font-weight: 600; font-size: 0.8125rem; }
        .hb-sum { margin-top: auto; font-weight: 600; font-variant-numeric: tabular-nums; }
        .hb-panel { border: 1px solid var(--hb-line); border-radius: 0.75rem; background: var(--hb-card); }
        .hb-panel > section { padding: 1rem; border-top: 1px solid var(--hb-line); }
        .hb-panel > section:first-child { border-top: 0; }
        .hb-panel h2 { font-size: 1.25rem; font-weight: 700; }
        .hb-panel h3 { font-weight: 600; margin-bottom: 0.5rem; }
        .hb-head { display: flex; align-items: start; justify-content: space-between; gap: 0.75rem; }
        .hb-rows { display: flex; flex-direction: column; }
        .hb-row { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.5rem 0; border-top: 1px solid var(--hb-line); }
        .hb-row:first-child { border-top: 0; }
        .hb-row > div:first-child { min-width: 0; }
        .hb-row small { color: var(--hb-soft); display: block; }
        .hb-cancelled { text-decoration: line-through; color: var(--hb-soft); }
        .hb-actions { display: flex; flex-wrap: wrap; gap: 0.4rem; justify-content: flex-end; }
        .hb-totals { display: grid; grid-template-columns: 1fr auto; gap: 0.25rem 1rem; margin-top: 0.75rem; font-variant-numeric: tabular-nums; }
        .hb-totals dt { color: var(--hb-soft); }
        .hb-totals .hb-due-line { font-weight: 700; font-size: 1.0625rem; color: inherit; }
        .hb-call-banner { background: var(--hb-tint); color: var(--hb-state); font-weight: 600; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
        .hb-add { display: grid; grid-template-columns: 1fr 5rem; gap: 0.5rem; margin-bottom: 0.5rem; }
        .hb-chips { display: flex; gap: 0.4rem; overflow-x: auto; padding-bottom: 0.5rem; }
        .hb-chips > * { flex-shrink: 0; white-space: nowrap; }
        .hb-dishes { max-height: 22rem; overflow-y: auto; }
        .hb-muted { color: var(--hb-soft); }
        .hb-legend { display: flex; flex-wrap: wrap; gap: 0.4rem 1.25rem; font-size: 0.8125rem; color: var(--hb-soft); }
        .hb-legend i { display: inline-block; width: 0.75rem; height: 0.75rem; border-radius: 0.2rem; margin-right: 0.4rem; vertical-align: -0.05rem; background: var(--hb-state); }
    </style>

    <div class="hb" wire:poll.10s>
        @if ($current === null)
            <p class="hb-muted">Немає жодного активного залу. Додайте зал і столики в розділі «Зали та столики».</p>
        @else
            @if ($halls->count() > 1)
                <div class="hb-toolbar" style="margin-bottom: 1rem">
                    @foreach ($halls as $hallOption)
                        @if ($hallOption->id === $current->id)
                            <x-filament::button wire:click="selectHall({{ $hallOption->id }})">{{ $hallOption->name }}</x-filament::button>
                        @else
                            <x-filament::button color="gray" wire:click="selectHall({{ $hallOption->id }})">{{ $hallOption->name }}</x-filament::button>
                        @endif
                    @endforeach
                </div>
            @endif

            <div @class(['hb-layout', 'hb-with-panel' => $panel !== null])>
                <div>
                    <div class="hb-grid">
                        @foreach ($cards as $card)
                            <button
                                type="button"
                                wire:key="table-{{ $card['table']->id }}"
                                wire:click="selectTable({{ $card['table']->id }})"
                                class="hb-table hb-{{ $card['state'] }}"
                                aria-pressed="{{ var_export($panel !== null && $panel['table']->id === $card['table']->id, true) }}"
                            >
                                <b>№{{ $card['table']->number }}</b>
                                <small>до {{ $card['table']->seats }} гостей</small>
                                <span class="hb-state">{{ HallBoard::STATES[$card['state']] }}</span>
                                @if ($card['due'] > 0)
                                    <span class="hb-sum">{{ Money::format($card['due'], 'uk') }}</span>
                                @elseif ($card['next'] !== null)
                                    <small class="hb-sum">бронь о {{ LocalTime::timeLabel($card['next']->starts_at) }}</small>
                                @endif
                            </button>
                        @endforeach
                    </div>

                    @if ($cards === [])
                        <p class="hb-muted">У цьому залі немає активних столиків.</p>
                    @endif

                    <div class="hb-legend" style="margin-top: 1rem">
                        @foreach (HallBoard::STATES as $state => $label)
                            <span class="hb-{{ $state }}"><i></i>{{ $label }}</span>
                        @endforeach
                    </div>
                </div>

                @if ($panel !== null)
                    @php
                        $order = $panel['order'];
                    @endphp

                    <aside class="hb-panel hb-{{ $panel['state'] }}" aria-label="Столик №{{ $panel['table']->number }}">
                        <section class="hb-head">
                            <div>
                                <h2>Столик №{{ $panel['table']->number }}</h2>
                                <span class="hb-state">{{ HallBoard::STATES[$panel['state']] }}</span>
                            </div>
                            <x-filament::button color="gray" size="sm" wire:click="selectTable(null)">Закрити</x-filament::button>
                        </section>

                        @if ($panel['hasCall'])
                            <section class="hb-call-banner">
                                <span>Гості кличуть офіціанта</span>
                                <x-filament::button size="sm" wire:click="resolveCall">Виклик виконано</x-filament::button>
                            </section>
                        @endif

                        @if ($panel['reservations']->isNotEmpty())
                            <section>
                                <h3>Бронювання сьогодні</h3>
                                <div class="hb-rows">
                                    @foreach ($panel['reservations'] as $reservation)
                                        <div class="hb-row" wire:key="reservation-{{ $reservation->id }}">
                                            <div>
                                                {{ LocalTime::timeLabel($reservation->starts_at) }}–{{ LocalTime::timeLabel($reservation->ends_at) }} · {{ $reservation->guest_name }}
                                                <small>гостей: {{ $reservation->guests_count }} · {{ $reservation->status->getLabel() }}</small>
                                            </div>
                                            @if ($reservation->status === ReservationStatus::Confirmed)
                                                <x-filament::button size="sm" wire:click="seat({{ $reservation->id }})">Посадити</x-filament::button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        @if ($order === null)
                            <section>
                                <p class="hb-muted" style="margin-bottom: 0.75rem">Відкритого замовлення немає.</p>
                                <x-filament::button wire:click="openOrder">Посадити без бронювання</x-filament::button>
                            </section>
                        @else
                            <section>
                                <h3>Замовлення № {{ $order->number }}</h3>

                                @if ($order->items->isEmpty())
                                    <p class="hb-muted">Позицій ще немає — додайте страви нижче.</p>
                                @else
                                    <div class="hb-rows">
                                        @foreach ($order->items as $item)
                                            <div class="hb-row" wire:key="item-{{ $item->id }}">
                                                <div @class(['hb-cancelled' => $item->status === OrderItemStatus::Cancelled])>
                                                    {{ $item->name }} × {{ $item->quantity }}
                                                    <small>{{ $item->status->getLabel() }} · {{ Money::format($item->total(), 'uk') }}</small>
                                                    @if ($item->comment)
                                                        <small>«{{ $item->comment }}»</small>
                                                    @endif
                                                </div>
                                                <div class="hb-actions">
                                                    @if ($item->status->canTransitionTo(OrderItemStatus::Served))
                                                        <x-filament::button size="xs" color="success" wire:click="markServed({{ $item->id }})">Подано</x-filament::button>
                                                    @endif
                                                    @if ($item->status === OrderItemStatus::New)
                                                        <x-filament::button size="xs" color="gray" wire:click="removeItem({{ $item->id }})">Прибрати</x-filament::button>
                                                    @elseif ($canCancel && $item->status !== OrderItemStatus::Cancelled)
                                                        <x-filament::button size="xs" color="danger" wire:click="cancelItem({{ $item->id }})" wire:confirm="Скасувати позицію «{{ $item->name }}»? Вона не увійде в рахунок.">Скасувати</x-filament::button>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <dl class="hb-totals">
                                    <dt>Сума</dt>
                                    <dd>{{ Money::format($order->subtotal, 'uk') }}</dd>
                                    <dt>Сплачено</dt>
                                    <dd>{{ Money::format($order->paid_total, 'uk') }}</dd>
                                    <dt class="hb-due-line">До сплати</dt>
                                    <dd class="hb-due-line">{{ Money::format($order->amountDue(), 'uk') }}</dd>
                                </dl>

                                <div class="hb-actions" style="justify-content: flex-start; margin-top: 0.75rem">
                                    @if ($order->amountDue() > 0)
                                        <x-filament::button size="sm" wire:click="acceptPayment('cash')" wire:confirm="Прийняти {{ Money::format($order->amountDue(), 'uk') }} готівкою?">Оплата готівкою</x-filament::button>
                                        <x-filament::button size="sm" wire:click="acceptPayment('terminal')" wire:confirm="Прийняти {{ Money::format($order->amountDue(), 'uk') }} через термінал?">Оплата терміналом</x-filament::button>
                                    @else
                                        <x-filament::button size="sm" color="success" wire:click="closeOrder">Закрити замовлення</x-filament::button>
                                    @endif
                                    @if ($canCancel && $order->paid_total === 0)
                                        <x-filament::button size="sm" color="danger" wire:click="cancelOrder" wire:confirm="Скасувати замовлення разом з усіма позиціями?">Скасувати замовлення</x-filament::button>
                                    @endif
                                </div>
                            </section>

                            <section>
                                <h3>Додати страву</h3>
                                <div class="hb-add">
                                    <x-filament::input.wrapper>
                                        <x-filament::input type="search" wire:model.live.debounce.300ms="search" placeholder="Пошук за назвою" aria-label="Пошук страви" />
                                    </x-filament::input.wrapper>
                                    <x-filament::input.wrapper>
                                        <x-filament::input type="number" min="1" max="99" wire:model="quantity" aria-label="Кількість" />
                                    </x-filament::input.wrapper>
                                </div>
                                <div style="margin-bottom: 0.5rem">
                                    <x-filament::input.wrapper>
                                        <x-filament::input type="text" maxlength="255" wire:model="comment" placeholder="Коментар для кухні (необов'язково)" aria-label="Коментар для кухні" />
                                    </x-filament::input.wrapper>
                                </div>
                                <div class="hb-chips">
                                    @if ($categoryId === null)
                                        <x-filament::button size="xs" wire:click="$set('categoryId', null)">Усі</x-filament::button>
                                    @else
                                        <x-filament::button size="xs" color="gray" wire:click="$set('categoryId', null)">Усі</x-filament::button>
                                    @endif
                                    @foreach ($panel['categories'] as $category)
                                        @if ($categoryId === $category->id)
                                            <x-filament::button size="xs" wire:click="$set('categoryId', {{ $category->id }})">{{ $category->name }}</x-filament::button>
                                        @else
                                            <x-filament::button size="xs" color="gray" wire:click="$set('categoryId', {{ $category->id }})">{{ $category->name }}</x-filament::button>
                                        @endif
                                    @endforeach
                                </div>
                                <div class="hb-rows hb-dishes">
                                    @foreach ($panel['dishes'] as $dish)
                                        <div class="hb-row" wire:key="dish-{{ $dish->id }}">
                                            <div>
                                                {{ $dish->name }}
                                                <small>{{ Money::format($dish->price, 'uk') }}</small>
                                            </div>
                                            <x-filament::button size="xs" color="gray" wire:click="addDish({{ $dish->id }})" aria-label="Додати: {{ $dish->name }}">Додати</x-filament::button>
                                        </div>
                                    @endforeach
                                    @if ($panel['dishes']->isEmpty())
                                        <p class="hb-muted">Нічого не знайдено.</p>
                                    @endif
                                </div>
                            </section>
                        @endif
                    </aside>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
