<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Reports\DashboardReport;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Виторг за 30 днів, грн';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isManagement();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $byDay = DashboardReport::revenueByDay(RevenueStats::MONTH);

        return [
            'datasets' => [
                [
                    'label' => 'Виторг',
                    'data' => array_map(fn (int $kopecks) => round($kopecks / 100, 2), array_values($byDay)),
                ],
            ],
            'labels' => array_map(fn (string $day) => CarbonImmutable::parse($day)->format('d.m'), array_keys($byDay)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
