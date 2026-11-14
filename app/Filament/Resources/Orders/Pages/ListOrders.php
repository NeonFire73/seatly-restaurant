<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Pages\HallBoard;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('hall')
                ->label('До залу')
                ->icon(Heroicon::OutlinedBuildingStorefront)
                ->color('gray')
                ->url(HallBoard::getUrl()),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'open' => Tab::make('Відкриті')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Open)),
            'all' => Tab::make('Усі'),
        ];
    }
}
