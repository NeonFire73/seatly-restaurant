<?php

namespace Database\Seeders;

use App\Enums\TableShape;
use App\Models\Hall;
use Illuminate\Database\Seeder;

class HallSeeder extends Seeder
{
    public function run(): void
    {
        $main = Hall::create([
            'name' => ['uk' => 'Основний зал', 'en' => 'Main hall'],
            'sort' => 1,
            'plan_width' => 1200,
            'plan_height' => 800,
        ]);

        $terrace = Hall::create([
            'name' => ['uk' => 'Тераса', 'en' => 'Terrace'],
            'sort' => 2,
            'plan_width' => 1000,
            'plan_height' => 600,
        ]);

        // [номер, місць, мінімум гостей, форма, x, y, ширина, висота]
        $mainTables = [
            ['1', 2, 1, TableShape::Round, 80, 80, 90, 90],
            ['2', 2, 1, TableShape::Round, 240, 80, 90, 90],
            ['3', 4, 2, TableShape::Square, 400, 80, 110, 110],
            ['4', 4, 2, TableShape::Square, 580, 80, 110, 110],
            ['5', 4, 2, TableShape::Square, 760, 80, 110, 110],
            ['6', 6, 3, TableShape::Rect, 80, 320, 200, 110],
            ['7', 6, 3, TableShape::Rect, 360, 320, 200, 110],
            ['8', 8, 4, TableShape::Rect, 640, 320, 260, 120],
            ['9', 2, 1, TableShape::Round, 80, 560, 90, 90],
            ['10', 4, 2, TableShape::Square, 400, 560, 110, 110],
        ];

        $terraceTables = [
            ['11', 2, 1, TableShape::Round, 80, 80, 90, 90],
            ['12', 2, 1, TableShape::Round, 260, 80, 90, 90],
            ['13', 4, 2, TableShape::Square, 440, 80, 110, 110],
            ['14', 4, 2, TableShape::Square, 80, 320, 110, 110],
            ['15', 6, 3, TableShape::Rect, 320, 320, 200, 110],
        ];

        foreach ([[$main, $mainTables], [$terrace, $terraceTables]] as [$hall, $tables]) {
            foreach ($tables as [$number, $seats, $min, $shape, $x, $y, $width, $height]) {
                $hall->tables()->create([
                    'number' => $number,
                    'seats' => $seats,
                    'min_guests' => $min,
                    'shape' => $shape,
                    'x' => $x,
                    'y' => $y,
                    'width' => $width,
                    'height' => $height,
                ]);
            }
        }
    }
}
