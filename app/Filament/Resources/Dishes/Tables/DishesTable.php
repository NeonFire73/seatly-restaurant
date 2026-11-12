<?php

namespace App\Filament\Resources\Dishes\Tables;

use App\Filament\Support\Translations;
use App\Models\Dish;
use App\Models\MenuCategory;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class DishesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category'))
            // Порядок страв задається всередині категорії, тому без групування список виглядав би перемішаним
            ->defaultGroup(
                Group::make('category.sort')
                    ->label('Категорія')
                    ->getTitleFromRecordUsing(fn (Dish $record) => $record->category->name)
                    ->collapsible(),
            )
            ->defaultSort('sort')
            ->paginated([25, 50, 'all'])
            ->defaultPaginationPageOption('all')
            ->reorderable('sort', fn () => Gate::allows('reorder', Dish::class))
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->disk('public')
                    ->square()
                    ->imageSize(40),
                TextColumn::make('name')
                    ->label('Назва')
                    ->weight('medium')
                    ->searchable(query: fn (Builder $query, string $search) => Translations::search($query, 'name', $search)),
                TextColumn::make('category.name')
                    ->label('Категорія')
                    ->color('gray'),
                TextColumn::make('price')
                    ->label('Ціна')
                    ->formatStateUsing(fn (int $state) => Money::format($state))
                    ->alignEnd()
                    ->sortable(),
                IconColumn::make('is_popular')
                    ->label('Популярна')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('')
                    ->trueColor('warning')
                    ->alignCenter(),
                ToggleColumn::make('is_available')
                    ->label('Є в наявності')
                    ->disabled(fn () => Gate::denies('toggleAvailability', Dish::class)),
            ])
            ->filters([
                SelectFilter::make('menu_category_id')
                    ->label('Категорія')
                    ->options(fn () => Translations::options(MenuCategory::orderBy('sort'))),
                TernaryFilter::make('is_available')
                    ->label('Наявність')
                    ->trueLabel('Є в наявності')
                    ->falseLabel('Немає'),
                TrashedFilter::make()
                    ->visible(fn () => Gate::allows('restoreAny', Dish::class)),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
