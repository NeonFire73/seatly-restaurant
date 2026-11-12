<?php

namespace App\Models;

use App\Enums\Allergen;
use App\Enums\DishTag;
use App\Enums\DishUnit;
use App\Models\Concerns\HasImageVariants;
use Database\Factories\DishFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

#[Fillable([
    'menu_category_id', 'name', 'description', 'price', 'weight', 'unit', 'cook_time_minutes',
    'tags', 'allergens', 'image', 'is_available', 'is_popular', 'is_preorderable', 'sort',
])]
class Dish extends Model
{
    /** @use HasFactory<DishFactory> */
    use HasFactory, HasImageVariants, HasTranslations, SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => DishUnit::class,
            'tags' => AsEnumCollection::of(DishTag::class),
            'allergens' => AsEnumCollection::of(Allergen::class),
            'is_available' => 'boolean',
            'is_popular' => 'boolean',
            'is_preorderable' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }
}
