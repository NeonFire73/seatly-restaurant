<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MenuCategory>
 */
class MenuCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $word = fake()->unique()->word();

        return [
            'name' => ['uk' => Str::ucfirst($word), 'en' => Str::ucfirst($word)],
            'slug' => Str::slug($word),
            'sort' => 0,
            'is_active' => true,
        ];
    }
}
