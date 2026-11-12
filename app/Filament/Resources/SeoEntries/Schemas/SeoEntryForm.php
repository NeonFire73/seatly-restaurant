<?php

namespace App\Filament\Resources\SeoEntries\Schemas;

use App\Filament\Support\Translations;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SeoEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Translations::tabs(fn (string $locale, bool $isMain) => [
                    TextInput::make("title.{$locale}")
                        ->label('Заголовок вкладки й пошуку (title)')
                        ->helperText('До 60 символів — довший пошуковики обрізають.')
                        ->required($isMain)
                        ->maxLength(70),
                    Textarea::make("description.{$locale}")
                        ->label('Опис для пошуку (description)')
                        ->helperText('До 160 символів.')
                        ->rows(3)
                        ->maxLength(200),
                ]),
                FileUpload::make('og_image')
                    ->label('Зображення для соцмереж')
                    ->helperText('Показується, коли посиланням діляться в месенджерах; найкраще 1200×630.')
                    ->image()
                    ->disk('public')
                    ->directory('seo')
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
            ]);
    }
}
