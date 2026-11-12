<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('seatly.demo.password');
        $domain = config('seatly.demo.email_domain');

        $users = [
            [UserRole::Owner, 'owner', 'Олена Коваль'],
            [UserRole::Manager, 'manager', 'Андрій Мельник'],
            [UserRole::Waiter, 'waiter', 'Ірина Бондар'],
            [UserRole::Waiter, 'waiter2', 'Максим Ткаченко'],
            [UserRole::Kitchen, 'kitchen', 'Сергій Шевчук'],
        ];

        foreach ($users as [$role, $login, $name]) {
            User::updateOrCreate(
                ['email' => "{$login}@{$domain}"],
                [
                    'name' => $name,
                    'password' => $password,
                    'role' => $role,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
