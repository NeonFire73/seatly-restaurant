<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'success' => Tab::make('Успішні')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PaymentStatus::Success)),
            'all' => Tab::make('Усі'),
        ];
    }
}
