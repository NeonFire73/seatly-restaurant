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
        ]);
    }
}
