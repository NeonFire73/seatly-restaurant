<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Reports\DashboardReport;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Гроші й неявки (частина 2, п. 7.8) — лише власникові й менеджерові.
 */
class RevenueStats extends StatsOverviewWidget
{
    // Період «за місяць» і частка неявок рахуються за стільки днів, разом із сьогоднішнім
    public const MONTH = 30;

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '60s';

    protected int|array|null $columns = 3;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isManagement();
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $noShows = DashboardReport::noShowRate(self::MONTH);

        return [
            Stat::make('Виторг за сьогодні', Money::format(DashboardReport::revenue(1), 'uk'))
                ->description('без чайових'),
            Stat::make('Виторг за 7 днів', Money::format(DashboardReport::revenue(7), 'uk'))
                ->description('без чайових'),
            Stat::make('Виторг за 30 днів', Money::format(DashboardReport::revenue(self::MONTH), 'uk'))
                ->description('без чайових'),
            Stat::make('Чайові за 30 днів', Money::format(DashboardReport::tips(self::MONTH), 'uk'))
                ->description('з онлайн-платежів'),
            Stat::make('Частка неявок за 30 днів', number_format($noShows['percent'], 1, ',', ' ').' %')
                ->description("Не прийшли: {$noShows['no_shows']} із {$noShows['total']} бронювань"),
        ];
    }
}
