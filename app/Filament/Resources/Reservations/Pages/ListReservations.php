<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Enums\ReservationStatus;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Support\LocalTime;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListReservations extends ListRecords
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('schedule')
                ->label('Розклад')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->url(ReservationResource::getUrl('schedule')),
            CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $today = LocalTime::now()->startOfDay();

        return [
            'today' => Tab::make('Сьогодні')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereBetween('starts_at', [$today->utc(), $today->endOfDay()->utc()])),
            'upcoming' => Tab::make('Майбутні')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('starts_at', '>', $today->endOfDay()->utc())
                    ->whereIn('status', ReservationStatus::live())),
            'all' => Tab::make('Усі'),
        ];
    }
}
