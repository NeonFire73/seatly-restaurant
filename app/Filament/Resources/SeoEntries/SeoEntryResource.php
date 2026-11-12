<?php

namespace App\Filament\Resources\SeoEntries;

use App\Filament\Resources\SeoEntries\Pages\EditSeoEntry;
use App\Filament\Resources\SeoEntries\Pages\ListSeoEntries;
use App\Filament\Resources\SeoEntries\Schemas\SeoEntryForm;
use App\Filament\Resources\SeoEntries\Tables\SeoEntriesTable;
use App\Models\SeoEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SeoEntryResource extends Resource
{
    protected static ?string $model = SeoEntry::class;

    // В українській з великої літери пишеться лише перше слово: «Блоки сторінок», а не «Блоки Сторінок»
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'content/seo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'Контент сайту';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'SEO сторінки';

    protected static ?string $pluralModelLabel = 'SEO';

    public static function getRecordTitle(?Model $record): string
    {
        if ($record instanceof SeoEntry) {
            return 'SEO: '.$record->label();
        }

        return 'SEO';
    }

    public static function form(Schema $schema): Schema
    {
        return SeoEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeoEntriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeoEntries::route('/'),
            'edit' => EditSeoEntry::route('/{record}/edit'),
        ];
    }
}
