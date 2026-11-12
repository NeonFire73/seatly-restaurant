<?php

namespace App\Filament\Resources\DiningTables;

use App\Filament\Resources\DiningTables\Pages\CreateDiningTable;
use App\Filament\Resources\DiningTables\Pages\EditDiningTable;
use App\Filament\Resources\DiningTables\Pages\ListDiningTables;
use App\Filament\Resources\DiningTables\Schemas\DiningTableForm;
use App\Filament\Resources\DiningTables\Tables\DiningTablesTable;
use App\Models\DiningTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DiningTableResource extends Resource
{
    protected static ?string $model = DiningTable::class;

    // В українській з великої літери пишеться лише перше слово: «Блоки сторінок», а не «Блоки Сторінок»
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'tables';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Зали та столики';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'столик';

    protected static ?string $pluralModelLabel = 'Столики';

    public static function getRecordTitle(?Model $record): string
    {
        if ($record instanceof DiningTable) {
            return "Столик №{$record->number}";
        }

        return 'Столик';
    }

    public static function form(Schema $schema): Schema
    {
        return DiningTableForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiningTablesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiningTables::route('/'),
            'create' => CreateDiningTable::route('/create'),
            'edit' => EditDiningTable::route('/{record}/edit'),
        ];
    }
}
