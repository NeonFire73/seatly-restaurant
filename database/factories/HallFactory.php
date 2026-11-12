<?php

namespace Database\Factories;

use App\Models\Hall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hall>
 */
class HallFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $word = fake()->unique()->word();

        return [
            'name' => ['uk' => 'Зал '.$word, 'en' => 'Hall '.$word],
            'sort' => 0,
            'is_active' => true,
            'plan_width' => 1200,
            'plan_height' => 800,
        ];
    }
}
