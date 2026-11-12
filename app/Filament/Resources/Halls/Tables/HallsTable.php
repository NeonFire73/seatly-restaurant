<?php

namespace App\Filament\Resources\Halls\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class HallsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Назва')
                    ->weight('medium'),
                TextColumn::make('tables_count')
                    ->label('Столиків')
                    ->counts('tables')
                    ->alignEnd(),
                TextColumn::make('tables_sum_seats')
                    ->label('Місць')
                    ->sum('tables', 'seats')
                    ->alignEnd(),
                ToggleColumn::make('is_active')
                    ->label('Працює'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
