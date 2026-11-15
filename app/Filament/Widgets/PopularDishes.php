<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Reports\DashboardReport;
use Filament\Widgets\Widget;

class PopularDishes extends Widget
{
    protected static ?int $sort = 4;

    protected string $view = 'filament.widgets.popular-dishes';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isManagement();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['dishes' => DashboardReport::popularDishes(RevenueStats::MONTH)];
    }
}
