<?php

namespace App\Filament\Resources\Halls\Schemas;

use App\Filament\Support\Translations;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HallForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Translations::tabs(fn (string $locale, bool $isMain) => [
                    TextInput::make("name.{$locale}")
                        ->label('Назва')
                        ->required($isMain)
                        ->maxLength(100),
                ]),
                Section::make('Полотно схеми залу')
                    ->description('Розмір області, на якій розставляються столики. Координати столиків рахуються від її лівого верхнього кута.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('plan_width')
                            ->label('Ширина')
                            ->suffix('px')
                            ->integer()
                            ->minValue(200)
                            ->maxValue(5000)
                            ->default(1200)
                            ->required(),
                        TextInput::make('plan_height')
                            ->label('Висота')
                            ->suffix('px')
                            ->integer()
                            ->minValue(200)
                            ->maxValue(5000)
                            ->default(800)
                            ->required(),
                    ]),
                Toggle::make('is_active')
                    ->label('Зал працює')
                    ->helperText('Столики вимкненого залу не пропонуються для бронювання.')
                    ->default(true),
            ]);
    }
}
