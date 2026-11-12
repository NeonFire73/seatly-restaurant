<?php

namespace App\Filament\Resources\DiningTables\Schemas;

use App\Enums\TableShape;
use App\Filament\Support\Translations;
use App\Models\Hall;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DiningTableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Столик')
                    ->columns(2)
                    ->schema([
                        Select::make('hall_id')
                            ->label('Зал')
                            ->options(fn () => Translations::options(Hall::orderBy('sort')))
                            ->required(),
                        TextInput::make('number')
                            ->label('Номер')
                            ->helperText('Його бачать гості й офіціанти; має бути унікальним у закладі.')
                            ->required()
                            ->maxLength(10)
                            ->unique(ignoreRecord: true),
                        TextInput::make('min_guests')
                            ->label('Гостей від')
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->required()
                            ->lte('seats'),
                        TextInput::make('seats')
                            ->label('Гостей до (місць)')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(30)
                            ->default(4)
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Доступний для бронювання')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
                Section::make('На схемі залу')
                    ->columns(2)
                    ->schema([
                        Select::make('shape')
                            ->label('Форма')
                            ->options(TableShape::class)
                            ->default(TableShape::Square)
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('x')
                            ->label('Відступ зліва')
                            ->suffix('px')
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        TextInput::make('y')
                            ->label('Відступ згори')
                            ->suffix('px')
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        TextInput::make('width')
                            ->label('Ширина')
                            ->suffix('px')
                            ->integer()
                            ->minValue(30)
                            ->default(100)
                            ->required(),
                        TextInput::make('height')
                            ->label('Висота')
                            ->suffix('px')
                            ->integer()
                            ->minValue(30)
                            ->default(100)
                            ->required(),
                        TextInput::make('rotation')
                            ->label('Поворот')
                            ->suffix('°')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(359)
                            ->default(0)
                            ->required(),
                    ]),
            ]);
    }
}
