<?php

namespace App\Filament\Resources\TextPages\Schemas;

use App\Filament\Support\Translations;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Translations::tabs(fn (string $locale, bool $isMain) => [
                    TextInput::make("title.{$locale}")
                        ->label('Заголовок')
                        ->required($isMain)
                        ->maxLength(150),
                    RichEditor::make("body.{$locale}")
                        ->label('Текст')
                        ->helperText('Небезпечна розмітка (скрипти, вбудовані зображення) вирізається під час збереження.')
                        ->required($isMain)
                        ->toolbarButtons([
                            ['bold', 'italic', 'link'],
                            ['h2', 'h3'],
                            ['bulletList', 'orderedList', 'blockquote'],
                            ['table'],
                            ['undo', 'redo'],
                        ]),
                ]),
            ]);
    }
}
