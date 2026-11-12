<?php

use App\Enums\UserRole;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Hall;
use App\Models\MenuCategory;
use App\Models\User;
use App\Models\WorkingHour;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkingHourSeeder;

it('наповнює базу демо-даними', function () {
    $this->seed();

    expect(Hall::count())->toBe(2)
        ->and(DiningTable::count())->toBe(15)
        ->and(DiningTable::pluck('qr_token')->unique())->toHaveCount(15)
        ->and(MenuCategory::count())->toBe(6)
        ->and(Dish::count())->toBeGreaterThanOrEqual(35)
        ->and(WorkingHour::count())->toBe(7);

    foreach (UserRole::cases() as $role) {
        expect(User::where('role', $role)->exists())->toBeTrue();
    }
});

it('можна запускати повторно для користувачів і годин роботи', function () {
    $this->seed();
    $this->seed([UserSeeder::class, WorkingHourSeeder::class]);

    expect(User::count())->toBe(5)
        ->and(WorkingHour::count())->toBe(7);
});
