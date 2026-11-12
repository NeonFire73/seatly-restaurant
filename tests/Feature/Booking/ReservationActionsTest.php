<?php

use App\Actions\Reservations\CancelReservation;
use App\Actions\Reservations\CompleteReservation;
use App\Actions\Reservations\CreateReservation;
use App\Actions\Reservations\MarkReservationNoShow;
use App\Actions\Reservations\MoveReservation;
use App\Actions\Reservations\ReservationData;
use App\Actions\Reservations\RestoreReservation;
use App\Actions\Reservations\SeatReservation;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exceptions\Booking\InvalidTransition;
use App\Exceptions\Booking\SlotUnavailable;
use App\Exceptions\Booking\TableUnavailable;
use App\Mail\ReservationCancelledMail;
use App\Mail\ReservationConfirmedMail;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Ics;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    openRestaurant();
    Mail::fake();
    $this->table = DiningTable::factory()->create(['seats' => 4, 'min_guests' => 1]);

    $this->data = fn (array $overrides = []) => new ReservationData(...[
        'tableId' => $this->table->id,
        'startsAt' => kyiv('2026-11-17 18:00'),
        'guests' => 2,
        'name' => 'Оксана',
        'phone' => '+380671234567',
        'email' => 'guest@example.com',
        'comment' => null,
        'locale' => 'uk',
        'source' => ReservationSource::Site,
        ...$overrides,
    ]);
});

it('створює підтверджене безкоштовне бронювання з кодом і токеном', function () {
    $reservation = app(CreateReservation::class)->handle(($this->data)());

    expect($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->fee_amount)->toBe(0)
        ->and($reservation->code)->toMatch('/^[A-HJ-NP-Z2-9]{6}$/')
        ->and(strlen($reservation->token))->toBe(48)
        // У базі час лежить в UTC: 18:00 за Києвом узимку — це 16:00
        ->and($reservation->starts_at->format('Y-m-d H:i'))->toBe('2026-11-17 16:00')
        ->and($reservation->ends_at->format('H:i'))->toBe('18:00');
});

it('із двох запитів на один столик і час створює рівно одне бронювання', function () {
    app(CreateReservation::class)->handle(($this->data)());

    expect(fn () => app(CreateReservation::class)->handle(($this->data)(['name' => 'Другий гість'])))
        ->toThrow(TableUnavailable::class);

    expect(Reservation::count())->toBe(1);
});

it('не дає забронювати час упритул до іншого бронювання', function () {
    app(CreateReservation::class)->handle(($this->data)());

    expect(fn () => app(CreateReservation::class)->handle(($this->data)(['startsAt' => kyiv('2026-11-17 20:00')])))
        ->toThrow(TableUnavailable::class);

    app(CreateReservation::class)->handle(($this->data)(['startsAt' => kyiv('2026-11-17 20:30')]));

    expect(Reservation::count())->toBe(2);
});

it('відхиляє з сайту час поза слотами, а персоналу дозволяє будь-який у години роботи', function () {
    expect(fn () => app(CreateReservation::class)->handle(($this->data)(['startsAt' => kyiv('2026-11-17 18:10')])))
        ->toThrow(SlotUnavailable::class);

    // Гість подзвонив і прийде за двадцять хвилин — на сайті це заборонив би мінімальний час до візиту
    $this->travelTo(kyiv('2026-11-17 18:00'));
    $reservation = app(CreateReservation::class)->handle(($this->data)([
        'startsAt' => kyiv('2026-11-17 18:20'),
        'source' => ReservationSource::Phone,
    ]));

    expect($reservation->exists)->toBeTrue();

    expect(fn () => app(CreateReservation::class)->handle(($this->data)([
        'startsAt' => kyiv('2026-11-18 09:00'),
        'source' => ReservationSource::Phone,
    ])))->toThrow(SlotUnavailable::class);
});

it('відхиляє столик, що не підходить за кількістю гостей або вимкнений', function () {
    expect(fn () => app(CreateReservation::class)->handle(($this->data)(['guests' => 5])))
        ->toThrow(TableUnavailable::class);

    $this->table->update(['is_active' => false]);

    expect(fn () => app(CreateReservation::class)->handle(($this->data)()))
        ->toThrow(TableUnavailable::class);
});

it('надсилає гостю лист мовою бронювання з подією календаря', function () {
    $reservation = app(CreateReservation::class)->handle(($this->data)(['locale' => 'en']));

    Mail::assertQueued(ReservationConfirmedMail::class, function (ReservationConfirmedMail $mail) use ($reservation) {
        return $mail->hasTo('guest@example.com')
            && $mail->locale === 'en'
            && $mail->reservation->is($reservation)
            && count($mail->attachments()) === 1;
    });

    app()->setLocale('en');
    $mail = new ReservationConfirmedMail($reservation);

    $mail->assertSeeInHtml($reservation->code)
        ->assertSeeInHtml('/en/booking/'.$reservation->token)
        ->assertSeeInHtml('Your table is booked');
});

it('не надсилає лист, якщо e-mail не вказано', function () {
    app(CreateReservation::class)->handle(($this->data)(['email' => null, 'source' => ReservationSource::Phone]));

    Mail::assertNothingQueued();
});

it('сповіщає власника й менеджера про нове бронювання, але не того, хто його створив', function () {
    $owner = User::factory()->owner()->create();
    $manager = User::factory()->manager()->create();
    $waiter = User::factory()->create();

    app(CreateReservation::class)->handle(($this->data)(['source' => ReservationSource::Phone, 'createdBy' => $manager->id]));

    expect($owner->notifications()->count())->toBe(1)
        ->and($manager->notifications()->count())->toBe(0)
        ->and($waiter->notifications()->count())->toBe(0);
});

it('формує подію календаря в UTC', function () {
    $reservation = app(CreateReservation::class)->handle(($this->data)());
    $ics = Ics::forReservation($reservation);

    expect($ics)->toContain('DTSTART:20261117T160000Z')
        ->toContain('DTEND:20261117T180000Z')
        ->toContain('UID:reservation-'.$reservation->code)
        ->toContain("\r\n");
});

it('скасовує бронювання, звільняє столик і надсилає лист', function () {
    $reservation = app(CreateReservation::class)->handle(($this->data)());

    app(CancelReservation::class)->handle($reservation, 'Змінилися плани');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($reservation->fresh()->cancelled_at)->not->toBeNull()
        ->and($reservation->fresh()->cancel_reason)->toBe('Змінилися плани');

    Mail::assertQueued(ReservationCancelledMail::class);

    // Столик знову вільний на той самий час
    app(CreateReservation::class)->handle(($this->data)());
    expect(Reservation::count())->toBe(2);
});

it('проводить бронювання дозволеними переходами', function () {
    $reservation = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-16 12:00'))->create();
    $this->travelTo(kyiv('2026-11-16 12:05'));

    app(SeatReservation::class)->handle($reservation);
    expect($reservation->status)->toBe(ReservationStatus::Seated)
        ->and($reservation->seated_at)->not->toBeNull();

    app(CompleteReservation::class)->handle($reservation);
    expect($reservation->status)->toBe(ReservationStatus::Completed)
        // Гості пішли раніше — столик звільняється одразу
        ->and($reservation->ends_at->equalTo(now()))->toBeTrue();
});

it('забороняє недопустимі переходи статусів', function (ReservationStatus $from, string $action) {
    $reservation = Reservation::factory()->for($this->table, 'table')->status($from)->create();

    expect(fn () => app($action)->handle($reservation))->toThrow(InvalidTransition::class);
    expect($reservation->fresh()->status)->toBe($from);
})->with([
    'завершене не скасувати' => [ReservationStatus::Completed, CancelReservation::class],
    'скасоване не посадити' => [ReservationStatus::Cancelled, SeatReservation::class],
    'підтверджене не завершити без посадки' => [ReservationStatus::Confirmed, CompleteReservation::class],
    'посаджене не позначити неявкою' => [ReservationStatus::Seated, MarkReservationNoShow::class],
    'прострочене не посадити' => [ReservationStatus::Expired, SeatReservation::class],
]);

it('повертає «не прийшов» у «підтверджено», лише якщо столик не віддали іншим', function () {
    $reservation = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:00'))->status(ReservationStatus::NoShow)->create();

    app(RestoreReservation::class)->handle($reservation);
    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);

    app(MarkReservationNoShow::class)->handle($reservation);
    Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:30'))->create();

    expect(fn () => app(RestoreReservation::class)->handle($reservation))->toThrow(TableUnavailable::class);
});

it('переносить бронювання на інший столик і час із перевіркою доступності', function () {
    $reservation = app(CreateReservation::class)->handle(($this->data)());
    $other = DiningTable::factory()->create(['seats' => 6, 'min_guests' => 1]);
    Reservation::factory()->for($other, 'table')->at(kyiv('2026-11-17 19:00'))->create();

    // Зсув на пів години на своєму ж столику: власне бронювання не заважає
    app(MoveReservation::class)->handle($reservation, $this->table->id, kyiv('2026-11-17 18:30'), 3);
    expect($reservation->fresh()->starts_at->format('H:i'))->toBe('16:30')
        ->and($reservation->fresh()->guests_count)->toBe(3);

    expect(fn () => app(MoveReservation::class)->handle($reservation, $other->id, kyiv('2026-11-17 18:30'), 3))
        ->toThrow(TableUnavailable::class);

    app(MoveReservation::class)->handle($reservation, $other->id, kyiv('2026-11-17 13:00'), 3);
    expect($reservation->fresh()->table_id)->toBe($other->id);
});

it('фонова задача позначає неявку через пів години після початку', function () {
    $late = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-16 12:00'))->create();
    $soon = Reservation::factory()->at(kyiv('2026-11-16 12:30'))->create();
    $seated = Reservation::factory()->at(kyiv('2026-11-16 12:00'))->status(ReservationStatus::Seated)->create();

    $this->travelTo(kyiv('2026-11-16 12:40'));
    $this->artisan('reservations:mark-no-show')->assertSuccessful();

    expect($late->fresh()->status)->toBe(ReservationStatus::NoShow)
        ->and($soon->fresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($seated->fresh()->status)->toBe(ReservationStatus::Seated);
});
