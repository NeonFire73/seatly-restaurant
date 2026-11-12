<?php

use App\Enums\UserRole;
use App\Models\User;

/**
 * Матриця доступу з розділу 7.1 ТЗ для розділів етапу 2.
 */
dataset('sections', [
    'страви' => ['/admin/dishes', [UserRole::Owner, UserRole::Manager, UserRole::Waiter, UserRole::Kitchen]],
    'категорії' => ['/admin/menu-categories', [UserRole::Owner, UserRole::Manager]],
    'зали' => ['/admin/halls', [UserRole::Owner, UserRole::Manager]],
    'столики' => ['/admin/tables', [UserRole::Owner, UserRole::Manager]],
    'персонал' => ['/admin/staff', [UserRole::Owner]],
    'бронювання' => ['/admin/reservations', [UserRole::Owner, UserRole::Manager, UserRole::Waiter]],
]);

it('відкриває розділ лише дозволеним ролям', function (string $url, array $allowed) {
    foreach (UserRole::cases() as $role) {
        $response = $this->actingAs(User::factory()->role($role)->create())->get($url);

        if (in_array($role, $allowed, true)) {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
    }
})->with('sections');

it('не пускає в панель вимкненого працівника', function () {
    $this->actingAs(User::factory()->owner()->inactive()->create())
        ->get('/admin')
        ->assertForbidden();
});

it('показує в меню лише доступні розділи', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Waiter]))
        ->get('/admin')
        ->assertOk()
        ->assertSee('Страви')
        ->assertDontSee('Категорії')
        ->assertDontSee('Персонал');
});
