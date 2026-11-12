<?php

namespace App\Filament\Resources\TextPages\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('title')
                    ->label('Заголовок')
                    ->weight('medium'),
                TextColumn::make('slug')
                    ->label('Адреса')
                    ->prefix('/')
                    ->color('gray'),
                TextColumn::make('updated_at')
                    ->label('Змінено')
                    ->since()
                    ->description(fn ($record) => $record->editor?->name),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
