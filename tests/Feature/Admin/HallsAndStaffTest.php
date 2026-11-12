<?php

use App\Enums\UserRole;
use App\Filament\Resources\DiningTables\Pages\CreateDiningTable;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Livewire\livewire;

it('створює столик із випадковим QR-токеном', function () {
    $this->actingAs(User::factory()->manager()->create());
    $hall = Hall::factory()->create();

    livewire(CreateDiningTable::class)
        ->fillForm([
            'hall_id' => $hall->id,
            'number' => '21',
            'min_guests' => 2,
            'seats' => 4,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(DiningTable::sole()->qr_token)->toHaveLength(40);
});

it('не приймає мінімум гостей, більший за кількість місць', function () {
    $this->actingAs(User::factory()->manager()->create());

    livewire(CreateDiningTable::class)
        ->fillForm([
            'hall_id' => Hall::factory()->create()->id,
            'number' => '22',
            'min_guests' => 6,
            'seats' => 4,
        ])
        ->call('create')
        ->assertHasFormErrors(['min_guests']);
});

it('перевипускає QR-токен столика', function () {
    $table = DiningTable::factory()->create();
    $old = $table->qr_token;

    $table->regenerateQrToken();

    expect($table->fresh()->qr_token)->not->toBe($old);
});

it('не дає видалити зал зі столиками', function () {
    $owner = User::factory()->owner()->create();
    $hall = DiningTable::factory()->create()->hall;

    expect($owner->can('delete', $hall))->toBeFalse()
        ->and($owner->can('delete', Hall::factory()->create()))->toBeTrue();
});

it('власник додає працівника з паролем', function () {
    $this->actingAs(User::factory()->owner()->create());

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Нова Офіціантка',
            'email' => 'new@seatly.test',
            'role' => UserRole::Waiter->value,
            'password' => 'secret-pass-123',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'new@seatly.test')->sole();

    expect($user->role)->toBe(UserRole::Waiter)
        ->and(Hash::check('secret-pass-123', $user->password))->toBeTrue();
});

it('власник не може видалити сам себе', function () {
    $owner = User::factory()->owner()->create();

    expect($owner->can('delete', $owner))->toBeFalse()
        ->and($owner->can('delete', User::factory()->create()))->toBeTrue();
});
