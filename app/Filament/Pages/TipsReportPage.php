<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Reports\TipsReport;
use App\Support\LocalTime;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * Звіт «Чайові» (частина 2, п. 7.1): власник і менеджер бачать усіх офіціантів, офіціант — лише себе.
 */
class TipsReportPage extends Page
{
    // Найдовший період звіту, у днях: захист від випадкового запиту за десять років
    private const MAX_DAYS = 366;

    protected string $view = 'filament.pages.tips-report';

    protected static ?string $slug = 'tips';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Чайові';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasRole(UserRole::Owner, UserRole::Manager, UserRole::Waiter);
    }

    public function mount(): void
    {
        [$from, $to] = $this->period();

        $this->from = $from->format('Y-m-d');
        $this->to = $to->format('Y-m-d');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        [$from, $to] = $this->period();
        /** @var User $user */
        $user = auth()->user();
        $waiterId = null;

        if (! $user->isManagement()) {
            $waiterId = $user->id;
        }

        $rows = TipsReport::rows($from, $to, $waiterId);
        $tips = array_sum(array_column($rows, 'tips'));
        $base = array_sum(array_column($rows, 'base'));

        return [
            'rows' => $rows,
            'total' => [
                'payments' => array_sum(array_column($rows, 'payments')),
                'tips' => $tips,
                'percent' => TipsReport::percent($tips, $base),
            ],
            'ownOnly' => $waiterId !== null,
        ];
    }

    /**
     * Дати приходять з адреси й полів форми, тож некоректні замінюються типовим періодом — з початку місяця до сьогодні.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function period(): array
    {
        $today = LocalTime::now()->startOfDay();
        $from = LocalTime::parse($this->from) ?? $today->startOfMonth();
        $to = LocalTime::parse($this->to) ?? $today;

        if ($to->lt($from)) {
            $to = $from;
        }

        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS);
        }

        return [$from, $to];
    }
}
