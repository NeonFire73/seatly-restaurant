<?php

namespace App\Filament\Resources\DiningTables\Tables;

use App\Filament\Support\Translations;
use App\Models\DiningTable;
use App\Models\Hall;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DiningTablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('hall'))
            ->defaultGroup(
                Group::make('hall.sort')
                    ->label('Зал')
                    ->getTitleFromRecordUsing(fn (DiningTable $record) => $record->hall->name)
                    ->collapsible(),
            )
            ->defaultSort('id')
            ->paginated(false)
            ->columns([
                TextColumn::make('number')
                    ->label('№')
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('seats')
                    ->label('Гостей')
                    ->formatStateUsing(function (DiningTable $record): string {
                        if ($record->min_guests === $record->seats) {
                            return (string) $record->seats;
                        }

                        return "{$record->min_guests}–{$record->seats}";
                    }),
                TextColumn::make('shape')
                    ->label('Форма')
                    ->color('gray'),
                ToggleColumn::make('is_active')
                    ->label('Доступний'),
            ])
            ->filters([
                SelectFilter::make('hall_id')
                    ->label('Зал')
                    ->options(fn () => Translations::options(Hall::orderBy('sort'))),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
