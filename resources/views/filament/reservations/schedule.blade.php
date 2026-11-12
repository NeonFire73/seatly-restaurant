{{-- Стилі тут, а не класами Tailwind: тема панелі Filament зібрана заздалегідь і довільних класів не містить.
     Кольори задано окремо для світлої й темної теми (частина 2, розділ 7). --}}
<x-filament-panels::page>
    <style>
        .sl-schedule { --sl-line: #e5e7eb; --sl-soft: #6b7280; --sl-row: #ffffff; --sl-head: #f9fafb; --sl-now: #dc2626; }
        .dark .sl-schedule { --sl-line: #3f3f46; --sl-soft: #a1a1aa; --sl-row: #18181b; --sl-head: #27272a; --sl-now: #f87171; }
        .sl-schedule { border: 1px solid var(--sl-line); border-radius: 0.75rem; overflow-x: auto; background: var(--sl-row); font-size: 0.8125rem; }
        .sl-inner { min-width: 56rem; }
        .sl-row { display: grid; grid-template-columns: 8.5rem 1fr; border-top: 1px solid var(--sl-line); }
        .sl-row:first-child { border-top: 0; }
        .sl-label { padding: 0.5rem 0.75rem; border-right: 1px solid var(--sl-line); display: flex; flex-direction: column; justify-content: center; }
        .sl-label small { color: var(--sl-soft); }
        .sl-hall { background: var(--sl-head); font-weight: 600; padding: 0.4rem 0.75rem; border-top: 1px solid var(--sl-line); }
        .sl-track { position: relative; min-height: 3rem; }
        .sl-head .sl-track { min-height: 2rem; background: var(--sl-head); }
        .sl-head .sl-label { background: var(--sl-head); }
        .sl-tick { position: absolute; top: 0; bottom: 0; border-left: 1px solid var(--sl-line); padding-left: 0.3rem; color: var(--sl-soft); line-height: 2rem; font-variant-numeric: tabular-nums; }
        .sl-now { position: absolute; top: 0; bottom: 0; width: 2px; background: var(--sl-now); z-index: 2; }
        .sl-bar { position: absolute; top: 0.3rem; bottom: 0.3rem; border-radius: 0.4rem; padding: 0.15rem 0.45rem; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; color: #ffffff; line-height: 1.15; z-index: 1; display: flex; flex-direction: column; justify-content: center; }
        .sl-bar:hover, .sl-bar:focus-visible { filter: brightness(1.12); outline: 2px solid currentColor; outline-offset: 1px; }
        .sl-bar b { font-weight: 600; overflow: hidden; text-overflow: ellipsis; }
        .sl-bar span { opacity: 0.9; font-size: 0.6875rem; overflow: hidden; text-overflow: ellipsis; }
        .sl-pending_payment { background: #b45309; }
        .sl-confirmed { background: #1d4ed8; }
        .sl-seated { background: #15803d; }
        .sl-completed { background: #52525b; }
        .sl-no_show { background: #b91c1c; }
        .sl-legend { display: flex; flex-wrap: wrap; gap: 0.5rem 1.25rem; font-size: 0.8125rem; color: var(--sl-soft); }
        .sl-legend i { display: inline-block; width: 0.75rem; height: 0.75rem; border-radius: 0.2rem; margin-right: 0.4rem; vertical-align: -0.05rem; }
        .sl-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
    </style>

    <div class="sl-toolbar">
        <x-filament::button color="gray" icon="heroicon-m-chevron-left" wire:click="shiftDay(-1)">Попередній день</x-filament::button>
        <x-filament::button color="gray" wire:click="goToToday">Сьогодні</x-filament::button>
        <x-filament::button color="gray" icon="heroicon-m-chevron-right" icon-position="after" wire:click="shiftDay(1)">Наступний день</x-filament::button>
        <x-filament::input.wrapper>
            <x-filament::input type="date" wire:model.live="date" aria-label="Дата" />
        </x-filament::input.wrapper>
    </div>

    @if ($isClosed)
        <p style="color: var(--sl-soft, #6b7280)">Цього дня заклад зачинено — онлайн-бронювання на нього недоступне.</p>
    @endif

    <div class="sl-schedule" wire:poll.30s>
        <div class="sl-inner">
            <div class="sl-row sl-head">
                <div class="sl-label">Столик</div>
                <div class="sl-track">
                    @foreach ($hours as $hour)
                        <span class="sl-tick" style="left: {{ $hour['left'] }}%">{{ $hour['label'] }}</span>
                    @endforeach
                </div>
            </div>

            @foreach ($halls as $hall)
                <div class="sl-hall">{{ $hall->name }}</div>

                @foreach ($hall->tables as $table)
                    <div class="sl-row" wire:key="row-{{ $table->id }}">
                        <div class="sl-label">
                            <strong>№{{ $table->number }}</strong>
                            <small>{{ $table->min_guests }}–{{ $table->seats }} гостей</small>
                        </div>
                        <div class="sl-track">
                            @foreach ($hours as $hour)
                                <span class="sl-tick" style="left: {{ $hour['left'] }}%" aria-hidden="true"></span>
                            @endforeach

                            @if ($nowLeft !== null)
                                <span class="sl-now" style="left: {{ $nowLeft }}%" aria-hidden="true"></span>
                            @endif

                            @foreach ($bars->get($table->id, []) as $bar)
                                <a
                                    href="{{ $bar['url'] }}"
                                    class="sl-bar sl-{{ $bar['status'] }}"
                                    style="left: {{ $bar['left'] }}%; width: {{ $bar['width'] }}%"
                                    title="{{ $bar['time'] }} · {{ $bar['name'] }} · гостей: {{ $bar['guests'] }} · {{ $bar['label'] }}"
                                >
                                    <b>{{ $bar['name'] }}</b>
                                    <span>{{ $bar['time'] }} · {{ $bar['guests'] }} ос. · {{ $bar['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>

    <div class="sl-schedule" style="border: 0; background: none; overflow: visible">
        <div class="sl-legend">
            <span><i class="sl-confirmed"></i>Підтверджено</span>
            <span><i class="sl-seated"></i>Гості за столиком</span>
            <span><i class="sl-completed"></i>Завершено</span>
            <span><i class="sl-no_show"></i>Не прийшов</span>
            <span><i class="sl-pending_payment"></i>Очікує оплати</span>
        </div>
    </div>
</x-filament-panels::page>
