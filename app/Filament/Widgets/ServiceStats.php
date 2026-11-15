<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Reports\DashboardReport;
use App\Support\LocalTime;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Що відбувається в залі просто зараз: бронювання на сьогодні й зайнятість. Бачать усі, хто працює із залом.
 */
class ServiceStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasRole(UserRole::Owner, UserRole::Manager, UserRole::Waiter);
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $reservations = DashboardReport::todayReservations();
        $occupancy = DashboardReport::occupancy();
        /** @var Reservation|null $next */
        $next = $reservations->first(fn (Reservation $reservation) => $reservation->starts_at->isFuture());
        $nextLabel = 'Найближчих бронювань немає';

        if ($next !== null) {
            $nextLabel = 'Найближче о '.LocalTime::timeLabel($next->starts_at).', столик №'.$next->table->number;
        }

        return [
            Stat::make('Бронювання на сьогодні', $reservations->count())
                ->description($nextLabel),
            Stat::make('Гостей очікується', (int) $reservations->sum('guests_count'))
                ->description('за бронюваннями на сьогодні'),
            Stat::make('Зайнятість залу', $occupancy['percent'].' %')
                ->description("Зайнято столиків: {$occupancy['busy']} із {$occupancy['total']}"),
        ];
    }
}
