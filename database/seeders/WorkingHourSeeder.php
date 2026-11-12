<?php

namespace Database\Seeders;

use App\Models\WorkingHour;
use Illuminate\Database\Seeder;

class WorkingHourSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 7) as $weekday) {
            $isWeekend = $weekday >= 5;

            WorkingHour::updateOrCreate(['weekday' => $weekday], [
                'opens_at' => '11:00',
                // У п'ятницю й вихідні заклад працює на годину довше
                'closes_at' => $isWeekend ? '00:00' : '23:00',
                'is_closed' => false,
            ]);
        }
    }
}
