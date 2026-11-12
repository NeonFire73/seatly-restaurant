<?php

namespace App\Filament\Resources\GalleryImages\Schemas;

use App\Filament\Support\Translations;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GalleryImageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('image')
                    ->label('Фото')
                    ->helperText('JPG, PNG або WebP до 5 МБ; зменшені копії створюються автоматично.')
                    ->image()
                    ->required()
                    ->disk('public')
                    ->directory('gallery')
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->columnSpanFull(),
                Translations::tabs(fn (string $locale, bool $isMain) => [
                    TextInput::make("alt.{$locale}")
                        ->label('Опис для незрячих (alt)')
                        ->helperText('Що зображено на фото, одним реченням.')
                        ->required($isMain)
                        ->maxLength(200),
                ]),
                Toggle::make('is_visible')
                    ->label('Показувати на сайті')
                    ->default(true),
            ]);
    }
}
