<?php

namespace App\Filament\Resources\TextPages;

use App\Filament\Resources\TextPages\Pages\EditPage;
use App\Filament\Resources\TextPages\Pages\ListPages;
use App\Filament\Resources\TextPages\Schemas\PageForm;
use App\Filament\Resources\TextPages\Tables\PagesTable;
use App\Models\Page;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    // В українській з великої літери пишеться лише перше слово: «Блоки сторінок», а не «Блоки Сторінок»
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'content/pages';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Контент сайту';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'текстову сторінку';

    protected static ?string $pluralModelLabel = 'Текстові сторінки';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
