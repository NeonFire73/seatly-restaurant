{{-- Звіт по чайових. Стилі власні — див. коментар у hall-board.blade.php --}}
@php
    use App\Support\Money;
@endphp

<x-filament-panels::page>
    <style>
        .tr { --tr-line: #e5e7eb; --tr-soft: #6b7280; --tr-card: #ffffff; --tr-head: #f9fafb; }
        .dark .tr { --tr-line: #3f3f46; --tr-soft: #a1a1aa; --tr-card: #18181b; --tr-head: #27272a; }
        .tr-filters { display: flex; flex-wrap: wrap; gap: 1rem; align-items: end; margin-bottom: 1.25rem; }
        .tr-filters label { display: grid; gap: 0.25rem; font-size: 0.875rem; font-weight: 500; }
        .tr-note { color: var(--tr-soft); font-size: 0.875rem; }
        .tr-wrap { border: 1px solid var(--tr-line); border-radius: 0.75rem; background: var(--tr-card); overflow-x: auto; }
        .tr table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        .tr th, .tr td { padding: 0.75rem 1rem; text-align: right; border-top: 1px solid var(--tr-line); white-space: nowrap; }
        .tr th:first-child, .tr td:first-child { text-align: left; white-space: normal; }
        .tr thead th { border-top: 0; background: var(--tr-head); font-size: 0.875rem; font-weight: 600; color: var(--tr-soft); }
        .tr tfoot td { font-weight: 700; background: var(--tr-head); }
        .tr-empty { padding: 2rem 1rem; text-align: center; color: var(--tr-soft); }
    </style>

    <div class="tr">
        <div class="tr-filters">
            <label>
                Від
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="from" />
                </x-filament::input.wrapper>
            </label>
            <label>
                До
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="to" />
                </x-filament::input.wrapper>
            </label>
            @if ($ownOnly)
                <p class="tr-note">Показано лише ваші чайові.</p>
            @endif
        </div>

        <div class="tr-wrap">
            @if ($rows === [])
                <p class="tr-empty">За цей період чайових не було.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Офіціант</th>
                            <th scope="col">Платежів із чайовими</th>
                            <th scope="col">Чайові</th>
                            <th scope="col">Середній відсоток</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>{{ $row['waiter'] }}</td>
                                <td>{{ $row['payments'] }}</td>
                                <td>{{ Money::format($row['tips'], 'uk') }}</td>
                                <td>{{ number_format($row['percent'], 1, ',', ' ') }} %</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Разом</td>
                            <td>{{ $total['payments'] }}</td>
                            <td>{{ Money::format($total['tips'], 'uk') }}</td>
                            <td>{{ number_format($total['percent'], 1, ',', ' ') }} %</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </div>
    </div>
</x-filament-panels::page>
