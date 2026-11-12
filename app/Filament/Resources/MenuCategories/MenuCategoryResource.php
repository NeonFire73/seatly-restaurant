<?php

namespace App\Filament\Resources\MenuCategories;

use App\Filament\Resources\MenuCategories\Pages\CreateMenuCategory;
use App\Filament\Resources\MenuCategories\Pages\EditMenuCategory;
use App\Filament\Resources\MenuCategories\Pages\ListMenuCategories;
use App\Filament\Resources\MenuCategories\Schemas\MenuCategoryForm;
use App\Filament\Resources\MenuCategories\Tables\MenuCategoriesTable;
use App\Models\MenuCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MenuCategoryResource extends Resource
{
    protected static ?string $model = MenuCategory::class;

    // В українській з великої літери пишеться лише перше слово: «Блоки сторінок», а не «Блоки Сторінок»
    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Меню';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'категорію';

    protected static ?string $pluralModelLabel = 'Категорії';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MenuCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MenuCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenuCategories::route('/'),
            'create' => CreateMenuCategory::route('/create'),
            'edit' => EditMenuCategory::route('/{record}/edit'),
        ];
    }
}
