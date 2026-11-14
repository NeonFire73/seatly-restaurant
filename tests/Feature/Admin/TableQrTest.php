<?php

use App\Filament\Resources\DiningTables\Pages\ListDiningTables;
use App\Models\DiningTable;
use App\Models\User;
use App\Support\QrCode;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->table = DiningTable::factory()->create(['number' => '4']);
});

it('будує QR-код у форматі SVG без заголовка XML', function () {
    $svg = QrCode::svg($this->table->qrUrl());

    expect($svg)->toStartWith('<svg')
        ->and($svg)->not->toContain('<?xml')
        ->and($this->table->qrUrl())->toEndWith('/t/'.$this->table->qr_token);
});

it('показує QR-код столика у вікні', function () {
    $this->actingAs(User::factory()->manager()->create());

    livewire(ListDiningTables::class)
        ->mountTableAction('showQr', $this->table)
        ->assertMountedActionModalSee('/t/'.$this->table->qr_token)
        ->assertMountedActionModalSeeHtml('<svg');
});

it('перевипускає токен столика після підтвердження', function () {
    $this->actingAs(User::factory()->manager()->create());
    $old = $this->table->qr_token;

    livewire(ListDiningTables::class)
        ->callTableAction('regenerateToken', $this->table)
        ->assertNotified();

    expect($this->table->fresh()->qr_token)->not->toBe($old)->toHaveLength(40);
});

it('друкує таблички вибраних або всіх активних столиків', function () {
    $second = DiningTable::factory()->create(['number' => '9']);
    $disabled = DiningTable::factory()->create(['number' => '15', 'is_active' => false]);
    $this->actingAs(User::factory()->manager()->create());

    $this->get('/admin/tables/qr-print')
        ->assertOk()
        ->assertSee('Столик № 4')
        ->assertSee('Столик № 9')
        ->assertDontSee('Столик № 15')
        ->assertSee('<svg', false);

    $this->get('/admin/tables/qr-print?ids='.$second->id.','.$disabled->id.',abc')
        ->assertOk()
        ->assertDontSee('Столик № 4')
        ->assertSee('Столик № 9')
        ->assertSee('Столик № 15');
});

it('сторінку друку відкривають лише власник і менеджер', function (string $role, int $status) {
    $this->actingAs(User::factory()->{$role}()->create());

    $this->get('/admin/tables/qr-print')->assertStatus($status);
})->with([
    ['owner', 200],
    ['manager', 200],
    ['waiter', 403],
    ['kitchen', 403],
]);

it('гостя й вимкненого працівника на сторінку друку не пускає', function () {
    $this->get('/admin/tables/qr-print')->assertRedirect('/admin/login');

    $this->actingAs(User::factory()->manager()->create(['is_active' => false]));

    $this->get('/admin/tables/qr-print')->assertForbidden();
});
