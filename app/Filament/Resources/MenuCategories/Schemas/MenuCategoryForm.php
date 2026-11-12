<?php

namespace App\Filament\Resources\MenuCategories\Schemas;

use App\Filament\Support\Translations;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class MenuCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Translations::tabs(fn (string $locale, bool $isMain) => [
                    TextInput::make("name.{$locale}")
                        ->label('Назва')
                        ->required($isMain)
                        ->maxLength(100)
                        ->live(onBlur: true)
                        // Адреса категорії латиницею, тому вона береться з англійської назви
                        ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($locale): void {
                            if ($locale === 'en' && blank($get('slug'))) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                ]),
                TextInput::make('slug')
                    ->label('Адреса (slug)')
                    ->helperText('Латиниця, цифри й дефіс; використовується в посиланні на категорію.')
                    ->required()
                    ->maxLength(100)
                    ->alphaDash()
                    ->unique(ignoreRecord: true),
                Toggle::make('is_active')
                    ->label('Показувати в меню')
                    ->default(true),
            ]);
    }
}
