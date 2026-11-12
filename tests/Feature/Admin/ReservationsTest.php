<?php

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Reservations\Pages\CreateReservation;
use App\Filament\Resources\Reservations\Pages\ListReservations;
use App\Filament\Resources\Reservations\Pages\ReservationSchedule;
use App\Filament\Resources\Reservations\Pages\ViewReservation;
use App\Filament\Resources\Reservations\Schemas\ReservationForm;
use App\Mail\ReservationCancelledMail;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

beforeEach(function () {
    openRestaurant();
    Mail::fake();
    $this->hall = Hall::factory()->create(['name' => ['uk' => 'Основний зал']]);
    $this->table = DiningTable::factory()->for($this->hall)->create(['number' => '7', 'seats' => 4, 'min_guests' => 1]);
    $this->manager = User::factory()->manager()->create();
});

it('відкриває сторінки бронювань лише дозволеним ролям', function (string $page, array $allowed) {
    $reservation = Reservation::factory()->for($this->table, 'table')->create();
    $url = str_replace('{id}', (string) $reservation->id, $page);

    foreach (UserRole::cases() as $role) {
        $response = $this->actingAs(User::factory()->role($role)->create())->get($url);

        if (in_array($role, $allowed, true)) {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
    }
})->with([
    'список' => ['/admin/reservations', [UserRole::Owner, UserRole::Manager, UserRole::Waiter]],
    'розклад' => ['/admin/reservations/schedule', [UserRole::Owner, UserRole::Manager, UserRole::Waiter]],
    'картка' => ['/admin/reservations/{id}', [UserRole::Owner, UserRole::Manager, UserRole::Waiter]],
    'створення' => ['/admin/reservations/create', [UserRole::Owner, UserRole::Manager]],
]);

it('розподіляє дії над бронюванням за ролями', function () {
    $reservation = Reservation::factory()->for($this->table, 'table')->create();
    $waiter = User::factory()->create();
    $kitchen = User::factory()->kitchen()->create();

    expect($waiter->can('seat', $reservation))->toBeTrue()
        ->and($waiter->can('cancel', $reservation))->toBeFalse()
        ->and($waiter->can('update', $reservation))->toBeFalse()
        ->and($waiter->can('markNoShow', $reservation))->toBeFalse()
        ->and($kitchen->can('view', $reservation))->toBeFalse()
        ->and($this->manager->can('cancel', $reservation))->toBeTrue()
        ->and($this->manager->can('delete', $reservation))->toBeFalse();
});

it('менеджер створює безкоштовне підтверджене бронювання за дзвінком гостя', function () {
    $this->actingAs($this->manager);
    // Гість прийде за двадцять хвилин: на сайті так забронювати не можна
    $this->travelTo(kyiv('2026-11-16 18:00'));

    livewire(CreateReservation::class)
        ->fillForm([
            'date' => '2026-11-16',
            'time' => '18:15',
            'guests_count' => 3,
            'table_id' => $this->table->id,
            'guest_name' => 'Тарас Савченко',
            'guest_phone' => '067 123 45 67',
            'source' => ReservationSource::Phone->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $reservation = Reservation::sole();

    expect($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->source)->toBe(ReservationSource::Phone)
        ->and($reservation->fee_amount)->toBe(0)
        ->and($reservation->created_by)->toBe($this->manager->id)
        ->and($reservation->guest_phone)->toBe('+380671234567')
        ->and($reservation->guest_email)->toBeNull()
        ->and($reservation->starts_at->format('Y-m-d H:i'))->toBe('2026-11-16 16:15');

    Mail::assertNothingQueued();
});

it('не створює бронювання на зайнятий столик', function () {
    $this->actingAs($this->manager);
    Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:00'))->create();

    livewire(CreateReservation::class)
        ->fillForm([
            'date' => '2026-11-17',
            'time' => '19:00',
            'guests_count' => 2,
            'table_id' => $this->table->id,
            'guest_name' => 'Тарас Савченко',
            'guest_phone' => '+380671234567',
            'source' => ReservationSource::Phone->value,
        ])
        ->call('create')
        // Зайнятого столика немає серед варіантів поля, тож форма відхиляє його ще до дії
        ->assertHasFormErrors(['table_id']);

    expect(Reservation::count())->toBe(1);
});

it('пропонує у формі лише вільні столики, що підходять', function () {
    $big = DiningTable::factory()->for($this->hall)->create(['seats' => 8, 'min_guests' => 4]);
    $busy = DiningTable::factory()->for($this->hall)->create(['seats' => 4, 'min_guests' => 1]);
    $reservation = Reservation::factory()->for($busy, 'table')->at(kyiv('2026-11-17 18:00'))->create();

    $options = ReservationForm::tableOptions('2026-11-17', '18:00', 2);

    expect(array_keys($options))->toBe([$this->table->id])
        ->and($options[$this->table->id])->toContain('№7')
        // Під час перенесення бронювання його власний столик лишається у списку
        ->and(ReservationForm::tableOptions('2026-11-17', '18:00', 2, $reservation->id))->toHaveKey($busy->id)
        ->and(ReservationForm::tableOptions('2026-11-17', '18:00', 6))->toHaveKey($big->id);
});

it('пропонує час лише в години роботи й не в минулому', function () {
    $this->travelTo(kyiv('2026-11-16 20:10'));

    $today = array_keys(ReservationForm::timeOptions('2026-11-16'));
    $tomorrow = array_keys(ReservationForm::timeOptions('2026-11-17'));

    expect($today[0])->toBe('19:45')
        ->and(end($today))->toBe('22:45')
        ->and($tomorrow[0])->toBe('11:00')
        ->and(ReservationForm::timeOptions(null))->toBe([]);
});

it('шукає бронювання за кодом, іменем і телефоном та фільтрує за статусом', function () {
    $this->actingAs($this->manager);
    $first = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 13:00'))->create(['guest_name' => 'Оксана Гриценко', 'guest_phone' => '+380671111111']);
    $second = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:00'))->status(ReservationStatus::Cancelled)->create(['guest_name' => 'Тарас Савченко']);

    livewire(ListReservations::class, ['activeTab' => 'all'])
        ->assertCanSeeTableRecords([$first, $second])
        ->searchTable($first->code)
        ->assertCanSeeTableRecords([$first])
        ->assertCanNotSeeTableRecords([$second])
        ->searchTable('Савченко')
        ->assertCanSeeTableRecords([$second])
        ->searchTable('671111111')
        ->assertCanSeeTableRecords([$first])
        ->searchTable()
        ->filterTable('status', [ReservationStatus::Cancelled->value])
        ->assertCanSeeTableRecords([$second])
        ->assertCanNotSeeTableRecords([$first]);
});

it('розкладає бронювання по вкладках і фільтрує за датою', function () {
    $this->actingAs($this->manager);
    $today = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-16 23:30'), 30)->create();
    $tomorrow = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 00:30'), 30)->create();
    $cancelled = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-18 18:00'))->status(ReservationStatus::Cancelled)->create();

    // Межа доби рахується за часом закладу, а не UTC
    livewire(ListReservations::class, ['activeTab' => 'today'])
        ->assertCanSeeTableRecords([$today])
        ->assertCanNotSeeTableRecords([$tomorrow, $cancelled]);

    livewire(ListReservations::class, ['activeTab' => 'upcoming'])
        ->assertCanSeeTableRecords([$tomorrow])
        ->assertCanNotSeeTableRecords([$today, $cancelled]);

    livewire(ListReservations::class, ['activeTab' => 'all'])
        ->filterTable('date', ['date' => '2026-11-17'])
        ->assertCanSeeTableRecords([$tomorrow])
        ->assertCanNotSeeTableRecords([$today, $cancelled]);
});

it('офіціант садить гостей, але не бачить керівних дій', function () {
    $this->actingAs(User::factory()->create());
    $reservation = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-16 12:00'))->create();
    $this->travelTo(kyiv('2026-11-16 12:05'));

    livewire(ListReservations::class, ['activeTab' => 'all'])
        ->assertTableActionVisible('seat', $reservation)
        ->assertTableActionHidden('cancel', $reservation)
        ->assertTableActionHidden('move', $reservation)
        ->assertTableActionHidden('noShow', $reservation)
        ->assertTableActionHidden('editGuest', $reservation)
        ->callTableAction('seat', $reservation)
        ->assertNotified();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Seated);
});

it('менеджер скасовує бронювання з причиною, гість отримує лист', function () {
    $this->actingAs($this->manager);
    $owner = User::factory()->owner()->create();
    $reservation = Reservation::factory()->for($this->table, 'table')->create();

    livewire(ViewReservation::class, ['record' => $reservation->getRouteKey()])
        ->callAction('cancel', ['reason' => 'Санітарний день'])
        ->assertNotified('Бронювання скасовано');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($reservation->fresh()->cancel_reason)->toBe('Санітарний день')
        ->and($owner->notifications()->count())->toBe(1)
        ->and($this->manager->notifications()->count())->toBe(0);

    Mail::assertQueued(ReservationCancelledMail::class);
});

it('менеджер позначає неявку, повертає її й завершує візит', function () {
    $this->actingAs($this->manager);
    $reservation = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-16 12:00'))->create();

    // До початку візиту позначити «не прийшов» не можна
    livewire(ViewReservation::class, ['record' => $reservation->getRouteKey()])->assertActionHidden('noShow');

    $this->travelTo(kyiv('2026-11-16 12:40'));

    livewire(ViewReservation::class, ['record' => $reservation->getRouteKey()])->callAction('noShow');
    expect($reservation->fresh()->status)->toBe(ReservationStatus::NoShow);

    livewire(ViewReservation::class, ['record' => $reservation->getRouteKey()])->callAction('restoreStatus');
    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);

    livewire(ViewReservation::class, ['record' => $reservation->getRouteKey()])->callAction('seat');
    livewire(ViewReservation::class, ['record' => $reservation->getRouteKey()])->callAction('complete');
    expect($reservation->fresh()->status)->toBe(ReservationStatus::Completed);
});

it('менеджер переносить бронювання, а на зайнятий столик — ні', function () {
    $this->actingAs($this->manager);
    $other = DiningTable::factory()->for($this->hall)->create(['seats' => 6, 'min_guests' => 1]);
    $reservation = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:00'))->create();
    Reservation::factory()->for($other, 'table')->at(kyiv('2026-11-18 18:00'))->create();

    livewire(ViewReservation::class, ['record' => $reservation->getRouteKey()])
        ->callAction('move', ['date' => '2026-11-17', 'time' => '13:00', 'guests_count' => 5, 'table_id' => $other->id])
        ->assertNotified('Бронювання перенесено');

    $reservation->refresh();

    expect($reservation->table_id)->toBe($other->id)
        ->and($reservation->guests_count)->toBe(5)
        ->and($reservation->starts_at->format('H:i'))->toBe('11:00');

    livewire(ViewReservation::class, ['record' => $reservation->getRouteKey()])
        ->callAction('move', ['date' => '2026-11-18', 'time' => '19:00', 'guests_count' => 5, 'table_id' => $other->id])
        ->assertHasActionErrors(['table_id']);

    expect($reservation->fresh()->starts_at->format('Y-m-d'))->toBe('2026-11-17');
});

it('менеджер змінює дані гостя', function () {
    $this->actingAs($this->manager);
    $reservation = Reservation::factory()->for($this->table, 'table')->create();

    livewire(ViewReservation::class, ['record' => $reservation->getRouteKey()])
        ->callAction('editGuest', ['guest_name' => 'Нове Ім\'я', 'guest_phone' => '0501112233', 'guest_email' => null, 'comment' => 'Дитяче крісло'])
        ->assertHasNoActionErrors();

    expect($reservation->fresh()->guest_name)->toBe('Нове Ім\'я')
        ->and($reservation->fresh()->guest_phone)->toBe('+380501112233')
        ->and($reservation->fresh()->comment)->toBe('Дитяче крісло');
});

it('показує бронювання дня в розкладі', function () {
    $this->actingAs(User::factory()->create());
    Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:00'))->create(['guest_name' => 'Оксана Гриценко']);
    Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 13:00'))->status(ReservationStatus::Cancelled)->create(['guest_name' => 'Скасований Гість']);
    Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-18 18:00'))->create(['guest_name' => 'Завтрашній Гість']);

    livewire(ReservationSchedule::class, ['date' => '2026-11-17'])
        ->assertSee('Оксана Гриценко')
        ->assertSee('18:00–20:00')
        ->assertSee('№7')
        ->assertDontSee('Скасований Гість')
        ->assertDontSee('Завтрашній Гість')
        ->call('shiftDay', 1)
        ->assertSet('date', '2026-11-18')
        ->assertSee('Завтрашній Гість')
        ->call('goToToday')
        ->assertSet('date', '2026-11-16');
});
