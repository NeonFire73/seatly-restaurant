{{-- Стилі власні — див. коментар у hall-board.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section heading="Найпопулярніші страви за 30 днів">
        <style>
            .pd { --pd-line: #e5e7eb; --pd-soft: #6b7280; }
            .dark .pd { --pd-line: #3f3f46; --pd-soft: #a1a1aa; }
            .pd ol { display: grid; margin: 0; padding: 0; list-style: none; }
            .pd li { display: flex; justify-content: space-between; gap: 1rem; padding: 0.625rem 0; border-top: 1px solid var(--pd-line); }
            .pd li:first-child { border-top: 0; padding-top: 0; }
            .pd-qty { color: var(--pd-soft); font-variant-numeric: tabular-nums; white-space: nowrap; }
            .pd-empty { color: var(--pd-soft); }
        </style>

        <div class="pd">
            @if ($dishes === [])
                <p class="pd-empty">Замовлень за цей період ще не було.</p>
            @else
                <ol>
                    @foreach ($dishes as $dish)
                        <li>
                            <span>{{ $loop->iteration }}. {{ $dish['name'] }}</span>
                            <span class="pd-qty">{{ $dish['quantity'] }} порц.</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
