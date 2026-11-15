{{-- Стилі власні — див. коментар у hall-board.blade.php --}}
@php
    use App\Filament\Resources\Reservations\ReservationResource;
    use App\Support\LocalTime;
@endphp

<x-filament-widgets::widget>
    <x-filament::section heading="Бронювання на сьогодні">
        <style>
            .td { --td-line: #e5e7eb; --td-soft: #6b7280; overflow-x: auto; }
            .dark .td { --td-line: #3f3f46; --td-soft: #a1a1aa; }
            .td table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
            .td th, .td td { padding: 0.625rem 0.75rem; text-align: left; border-top: 1px solid var(--td-line); white-space: nowrap; }
            .td thead th { border-top: 0; font-size: 0.875rem; font-weight: 600; color: var(--td-soft); }
            .td a { text-decoration: underline; }
            .td-note { margin-top: 0.75rem; color: var(--td-soft); font-size: 0.875rem; }
        </style>

        <div class="td">
            @if ($reservations->isEmpty())
                <p class="td-note">На сьогодні бронювань немає.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Час</th>
                            <th scope="col">Столик</th>
                            <th scope="col">Гість</th>
                            <th scope="col">Гостей</th>
                            <th scope="col">Стан</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reservations as $reservation)
                            <tr>
                                <td>{{ LocalTime::timeLabel($reservation->starts_at) }}</td>
                                <td>№{{ $reservation->table->number }}</td>
                                <td>
                                    <a href="{{ ReservationResource::getUrl('view', ['record' => $reservation]) }}">{{ $reservation->guest_name }}</a>
                                </td>
                                <td>{{ $reservation->guests_count }}</td>
                                <td>{{ $reservation->status->getLabel() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($more > 0)
                    <p class="td-note">І ще {{ $more }} — у розділі «Бронювання».</p>
                @endif
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
