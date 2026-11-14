<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Події моделей тут не вимикаються: під час створення столика подія creating генерує qr_token.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            HallSeeder::class,
            MenuSeeder::class,
            WorkingHourSeeder::class,
            ContentSeeder::class,
            // Після залів і годин роботи: бронювання ставляться на столики в робочі дні
            ReservationSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
