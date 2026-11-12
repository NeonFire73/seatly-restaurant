<?php

namespace App\Filament\Resources\ContentBlocks\Schemas;

use App\Filament\Support\Translations;
use App\Models\ContentBlock;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Різні блоки використовують різні поля (config/seatly.php → content_blocks), тож показуються лише потрібні.
 */
class ContentBlockForm
{
    public static function configure(Schema $schema): Schema
    {
        $uses = fn (string $field) => fn (?ContentBlock $record): bool => $record?->usesField($field) ?? false;

        return $schema
            ->columns(3)
            ->components([
                Section::make('Текст')
                    ->columnSpan(2)
                    ->schema([
                        Translations::tabs(fn (string $locale, bool $isMain) => [
                            TextInput::make("title.{$locale}")
                                ->label('Заголовок')
                                ->maxLength(150)
                                ->required($isMain)
                                ->visible($uses('title')),
                            Textarea::make("subtitle.{$locale}")
                                ->label('Підзаголовок')
                                ->rows(2)
                                ->maxLength(300)
                                ->visible($uses('subtitle')),
                            Textarea::make("body.{$locale}")
                                ->label('Текст')
                                ->helperText('Порожній рядок починає новий абзац.')
                                ->rows(6)
                                ->maxLength(3000)
                                ->visible($uses('body')),
                            TextInput::make("button_label.{$locale}")
                                ->label('Напис на кнопці')
                                ->maxLength(40)
                                ->visible($uses('button_label')),
                        ]),
                    ]),
                Section::make('Оформлення')
                    ->columnSpan(1)
                    ->schema([
                        Toggle::make('is_visible')
                            ->label('Показувати на сайті'),
                        TextInput::make('button_url')
                            ->label('Посилання кнопки')
                            ->helperText('Порожнє — кнопка веде на стандартну сторінку (бронювання або «Про ресторан»).')
                            ->maxLength(255)
                            ->rule('regex:/^(https?:\/\/|\/)/')
                            ->validationMessages(['regex' => 'Вкажіть повну адресу (https://…) або шлях на сайті (/menu).'])
                            ->visible($uses('button_url')),
                        FileUpload::make('image')
                            ->label('Зображення')
                            ->helperText('JPG, PNG або WebP до 5 МБ; зменшені копії створюються автоматично.')
                            ->image()
                            ->disk('public')
                            ->directory('content')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->visible($uses('image')),
                    ]),
            ]);
    }
}
