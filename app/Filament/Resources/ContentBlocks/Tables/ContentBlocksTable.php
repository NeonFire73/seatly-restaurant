<?php

namespace App\Filament\Resources\ContentBlocks\Tables;

use App\Models\ContentBlock;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContentBlocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('editor'))
            ->defaultSort('id')
            ->paginated(false)
            ->columns([
                TextColumn::make('key')
                    ->label('Блок')
                    ->formatStateUsing(fn (ContentBlock $record) => $record->label())
                    ->description(fn (ContentBlock $record) => $record->title)
                    ->weight('medium'),
                ImageColumn::make('image')
                    ->label('')
                    ->disk('public')
                    ->imageSize(40),
                ToggleColumn::make('is_visible')
                    ->label('Показувати'),
                TextColumn::make('updated_at')
                    ->label('Змінено')
                    ->since()
                    ->description(fn (ContentBlock $record) => $record->editor?->name),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
