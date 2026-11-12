<?php

namespace App\Filament\Resources\MenuCategories\Tables;

use App\Filament\Support\Translations;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MenuCategoriesTable
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
                    ->searchable(query: fn (Builder $query, string $search) => Translations::search($query, 'name', $search)),
                TextColumn::make('slug')
                    ->label('Адреса')
                    ->color('gray'),
                TextColumn::make('dishes_count')
                    ->label('Страв')
                    ->counts('dishes')
                    ->alignEnd(),
                ToggleColumn::make('is_active')
                    ->label('Показувати'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
