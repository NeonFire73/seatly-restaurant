<?php

namespace App\Filament\Resources\Halls;

use App\Filament\Resources\Halls\Pages\CreateHall;
use App\Filament\Resources\Halls\Pages\EditHall;
use App\Filament\Resources\Halls\Pages\ListHalls;
use App\Filament\Resources\Halls\Schemas\HallForm;
use App\Filament\Resources\Halls\Tables\HallsTable;
use App\Models\Hall;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class HallResource extends Resource
{
    protected static ?string $model = Hall::class;

    // В українській з великої літери пишеться лише перше слово: «Блоки сторінок», а не «Блоки Сторінок»
    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = 'Зали та столики';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'зал';

    protected static ?string $pluralModelLabel = 'Зали';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return HallForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HallsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHalls::route('/'),
            'create' => CreateHall::route('/create'),
            'edit' => EditHall::route('/{record}/edit'),
        ];
    }
}
