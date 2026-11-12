<?php

namespace Database\Factories;

use App\Enums\TableShape;
use App\Models\DiningTable;
use App\Models\Hall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiningTable>
 */
class DiningTableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hall_id' => Hall::factory(),
            'number' => (string) fake()->unique()->numberBetween(1, 999),
            'seats' => 4,
            'min_guests' => 1,
            'shape' => TableShape::Square,
            'x' => fake()->numberBetween(0, 1000),
            'y' => fake()->numberBetween(0, 600),
            'width' => 100,
            'height' => 100,
            'rotation' => 0,
            'is_active' => true,
        ];
    }
}
