<?php

namespace App\Filament\Resources\Dishes\Schemas;

use App\Enums\Allergen;
use App\Enums\DishTag;
use App\Enums\DishUnit;
use App\Filament\Support\Translations;
use App\Models\MenuCategory;
use App\Support\Money;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DishForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Опис')
                    ->columnSpan(2)
                    ->schema([
                        Translations::tabs(fn (string $locale, bool $isMain) => [
                            TextInput::make("name.{$locale}")
                                ->label('Назва')
                                ->required($isMain)
                                ->maxLength(150),
                            Textarea::make("description.{$locale}")
                                ->label('Склад і опис')
                                ->rows(3)
                                ->maxLength(1000),
                        ]),
                        FileUpload::make('image')
                            ->label('Фото')
                            ->image()
                            ->disk('public')
                            ->directory('dishes')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                    ]),
                Section::make('Ціна й подача')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('menu_category_id')
                            ->label('Категорія')
                            ->options(fn () => Translations::options(MenuCategory::orderBy('sort')))
                            ->required(),
                        TextInput::make('price')
                            ->label('Ціна')
                            ->suffix('₴')
                            ->required()
                            ->regex('/^\d{1,6}([.,]\d{1,2})?$/')
                            ->validationMessages(['regex' => 'Введіть суму в гривнях, наприклад 185 або 185.50.'])
                            ->formatStateUsing(fn (?int $state) => Money::toInput($state))
                            ->dehydrateStateUsing(fn (string $state) => Money::toKopecks($state)),
                        TextInput::make('weight')
                            ->label('Вихід')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(10000),
                        Select::make('unit')
                            ->label('Одиниця')
                            ->options(DishUnit::class)
                            ->default(DishUnit::Gram)
                            ->requiredWith('weight'),
                        TextInput::make('cook_time_minutes')
                            ->label('Час приготування')
                            ->helperText('Потрібен кухні, щоб вчасно почати попереднє замовлення.')
                            ->suffix('хв')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(240)
                            ->default(15)
                            ->required(),
                        Toggle::make('is_available')
                            ->label('Є в наявності')
                            ->default(true),
                        Toggle::make('is_popular')
                            ->label('Популярна'),
                        Toggle::make('is_preorderable')
                            ->label('Доступна для попереднього замовлення'),
                    ]),
                Section::make('Позначки й алергени')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        CheckboxList::make('tags')
                            ->label('Позначки')
                            ->options(DishTag::class)
                            ->columns(2),
                        CheckboxList::make('allergens')
                            ->label('Алергени')
                            ->options(Allergen::class)
                            ->columns(3),
                    ]),
            ]);
    }
}
