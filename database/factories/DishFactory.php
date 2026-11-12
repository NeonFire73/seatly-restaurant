<?php

namespace Database\Factories;

use App\Enums\DishUnit;
use App\Models\Dish;
use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dish>
 */
class DishFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'menu_category_id' => MenuCategory::factory(),
            'name' => ['uk' => $name, 'en' => $name],
            'description' => ['uk' => fake()->sentence()],
            'price' => fake()->numberBetween(80, 600) * 100,
            'weight' => 250,
            'unit' => DishUnit::Gram,
            'cook_time_minutes' => 15,
            'tags' => [],
            'allergens' => [],
            'is_available' => true,
            'is_popular' => false,
            'is_preorderable' => true,
            'sort' => 0,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => ['is_available' => false]);
    }
}
