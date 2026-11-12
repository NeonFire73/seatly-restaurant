<?php

namespace App\Filament\Resources\SeoEntries\Tables;

use App\Models\SeoEntry;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeoEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->paginated(false)
            ->columns([
                TextColumn::make('page_key')
                    ->label('Сторінка')
                    ->formatStateUsing(fn (SeoEntry $record) => $record->label())
                    ->weight('medium'),
                TextColumn::make('title')
                    ->label('Заголовок')
                    ->description(fn (SeoEntry $record) => $record->description)
                    ->wrap(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
