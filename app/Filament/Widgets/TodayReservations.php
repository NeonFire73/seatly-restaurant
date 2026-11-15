<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Reports\DashboardReport;
use Filament\Widgets\Widget;

/**
 * Найближчі бронювання на сьогодні — разом із ServiceStats це «спрощений дашборд» офіціанта (частина 2, п. 7.1).
 */
class TodayReservations extends Widget
{
    // Більше рядків на дашборді не читається; повний список — у розділі «Бронювання»
    private const LIMIT = 8;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.today-reservations';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasRole(UserRole::Owner, UserRole::Manager, UserRole::Waiter);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $reservations = DashboardReport::todayReservations();

        return [
            'reservations' => $reservations->take(self::LIMIT),
            'more' => max(0, $reservations->count() - self::LIMIT),
        ];
    }
}
