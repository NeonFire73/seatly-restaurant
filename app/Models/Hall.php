<?php

namespace App\Models;

use Database\Factories\HallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

#[Fillable(['name', 'sort', 'is_active', 'plan_width', 'plan_height'])]
class Hall extends Model
{
    /** @use HasFactory<HallFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<DiningTable, $this>
     */
    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }
}
