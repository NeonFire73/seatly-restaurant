<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Enums\ReservationStatus;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\Hall;
use App\Models\Reservation;
use App\Services\Booking\AvailabilityService;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

/**
 * Вигляд «Розклад» (частина 2, п. 7.4): столики по вертикалі, час по горизонталі, бронювання — смуги.
 */
class ReservationSchedule extends Page
{
    protected static string $resource = ReservationResource::class;

    protected string $view = 'filament.reservations.schedule';

    protected static ?string $title = 'Розклад бронювань';

    protected static ?string $breadcrumb = 'Розклад';

    #[Url]
    public string $date = '';

    public function mount(): void
    {
        if (LocalTime::parse($this->date) === null) {
            $this->date = LocalTime::now()->format('Y-m-d');
        }
    }

    public function shiftDay(int $days): void
    {
        $this->date = $this->day()->addDays(max(-1, min(1, $days)))->format('Y-m-d');
    }

    public function goToToday(): void
    {
        $this->date = LocalTime::now()->format('Y-m-d');
    }

    public function getSubheading(): string
    {
        return Str::ucfirst($this->day()->locale('uk')->isoFormat('dddd, D MMMM YYYY'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('list')
                ->label('Списком')
                ->icon(Heroicon::OutlinedListBullet)
                ->color('gray')
                ->url(ReservationResource::getUrl()),
            Action::make('create')
                ->label('Створити бронювання')
                ->url(ReservationResource::getUrl('create'))
                ->visible(fn () => ReservationResource::canCreate()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $day = $this->day();
        $window = app(AvailabilityService::class)->dayWindow($day);

        // У вихідний показуємо умовну шкалу, щоб було видно бронювання, створені до зміни графіка
        $from = $window[0] ?? $day->setTime(10, 0);
        $to = $window[1] ?? $day->setTime(23, 0);
        $total = $from->diffInMinutes($to);

        $halls = Hall::query()
            ->orderBy('sort')
            ->with(['tables' => fn ($query) => $query->orderBy('id')])
            ->get();

        $bars = Reservation::query()
            ->whereNotIn('status', [ReservationStatus::Cancelled, ReservationStatus::Expired])
            ->where('starts_at', '<', $to->utc())
            ->where('ends_at', '>', $from->utc())
            ->orderBy('starts_at')
            ->get()
            ->map(function (Reservation $reservation) use ($from, $to, $total): array {
                $start = LocalTime::of($reservation->starts_at)->max($from);
                $end = LocalTime::of($reservation->ends_at)->min($to);

                return [
                    'table_id' => $reservation->table_id,
                    'left' => round($from->diffInMinutes($start) / $total * 100, 3),
                    'width' => round($start->diffInMinutes($end) / $total * 100, 3),
                    'status' => $reservation->status->value,
                    'time' => LocalTime::timeLabel($reservation->starts_at).'–'.LocalTime::timeLabel($reservation->ends_at),
                    'name' => $reservation->guest_name,
                    'guests' => $reservation->guests_count,
                    'label' => $reservation->status->getLabel(),
                    'url' => ReservationResource::getUrl('view', ['record' => $reservation]),
                ];
            })
            ->groupBy('table_id');

        $hours = [];

        for ($hour = $from->startOfHour(); $hour->lt($to); $hour = $hour->addHour()) {
            if ($hour->gte($from)) {
                $hours[] = ['label' => $hour->format('H:i'), 'left' => round($from->diffInMinutes($hour) / $total * 100, 3)];
            }
        }

        $now = LocalTime::now();
        $nowLeft = null;

        if ($now->gte($from) && $now->lt($to)) {
            $nowLeft = round($from->diffInMinutes($now) / $total * 100, 3);
        }

        return [
            'halls' => $halls,
            'bars' => $bars,
            'hours' => $hours,
            'nowLeft' => $nowLeft,
            'isClosed' => $window === null,
        ];
    }

    private function day(): CarbonImmutable
    {
        return LocalTime::parse($this->date) ?? LocalTime::now()->startOfDay();
    }
}
