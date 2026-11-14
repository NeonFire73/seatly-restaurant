{{-- Екран кухні: великий шрифт, дві колонки, автооновлення. Стилі власні — див. коментар у hall-board.blade.php --}}
@php
    use App\Enums\OrderItemStatus;
    use App\Support\LocalTime;
@endphp

<x-filament-panels::page>
    <style>
        .kb { --kb-line: #e5e7eb; --kb-soft: #6b7280; --kb-card: #ffffff; --kb-cooking: #b45309; --kb-tint: #fef3c7; }
        .dark .kb { --kb-line: #3f3f46; --kb-soft: #a1a1aa; --kb-card: #18181b; --kb-cooking: #fbbf24; --kb-tint: #422006; }
        .kb { display: grid; gap: 1.5rem; align-items: start; font-size: 1.125rem; }
        @media (min-width: 64rem) { .kb { grid-template-columns: 3fr 2fr; } }
        .kb h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; }
        .kb h2 span { color: var(--kb-soft); font-weight: 500; }
        .kb-list { display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); }
        .kb-card { border: 1px solid var(--kb-line); border-radius: 0.75rem; background: var(--kb-card); padding: 1rem; display: flex; flex-direction: column; gap: 0.35rem; }
        .kb-card.kb-cooking { border-color: var(--kb-cooking); background: var(--kb-tint); }
        .kb-meta { display: flex; justify-content: space-between; gap: 0.75rem; color: var(--kb-soft); font-size: 1rem; }
        .kb-dish { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
        .kb-comment { font-style: italic; }
        .kb-status { color: var(--kb-cooking); font-weight: 600; font-size: 1rem; }
        .kb-actions { margin-top: 0.5rem; display: flex; gap: 0.5rem; }
        .kb-empty { color: var(--kb-soft); }
    </style>

    <div class="kb" wire:poll.10s>
        <section aria-labelledby="kb-now">
            <h2 id="kb-now">Зараз <span>({{ count($now) }})</span></h2>

            @if ($now === [])
                <p class="kb-empty">Немає позицій у роботі.</p>
            @endif

            <div class="kb-list">
                @foreach ($now as $card)
                    @php
                        $item = $card['item'];
                    @endphp
                    <article wire:key="now-{{ $item->id }}" @class(['kb-card', 'kb-cooking' => $item->status === OrderItemStatus::Cooking])>
                        <div class="kb-meta">
                            <span>Столик №{{ $card['table'] }}</span>
                            <span>{{ $card['minutes'] }} хв тому</span>
                        </div>
                        <p class="kb-dish">{{ $item->name }} × {{ $item->quantity }}</p>
                        @if ($item->comment)
                            <p class="kb-comment">«{{ $item->comment }}»</p>
                        @endif
                        @if ($item->status === OrderItemStatus::Cooking)
                            <p class="kb-status">Готується</p>
                        @endif
                        <div class="kb-actions">
                            @if ($item->status === OrderItemStatus::New)
                                <x-filament::button size="lg" color="warning" wire:click="startCooking({{ $item->id }})">Готується</x-filament::button>
                            @endif
                            <x-filament::button size="lg" color="success" wire:click="markReady({{ $item->id }})">Готово</x-filament::button>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="kb-scheduled">
            <h2 id="kb-scheduled">До часу <span>({{ count($scheduled) }})</span></h2>

            @if ($scheduled === [])
                <p class="kb-empty">Попередніх замовлень на сьогодні немає.</p>
            @endif

            <div class="kb-list">
                @foreach ($scheduled as $card)
                    @php
                        $item = $card['item'];
                    @endphp
                    <article wire:key="scheduled-{{ $item->id }}" class="kb-card">
                        <div class="kb-meta">
                            <span>Столик №{{ $card['table'] }}</span>
                            <span>почати о {{ LocalTime::timeLabel($card['startAt']) }}</span>
                        </div>
                        <p class="kb-dish">{{ $item->name }} × {{ $item->quantity }}</p>
                        @if ($item->comment)
                            <p class="kb-comment">«{{ $item->comment }}»</p>
                        @endif
                        <div class="kb-actions">
                            <x-filament::button size="lg" color="warning" wire:click="startCooking({{ $item->id }})">Готується</x-filament::button>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
