<?php

use App\Enums\ReservationStatus;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\SpecialDay;
use App\Models\WorkingHour;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\TableHold;
use App\Services\SiteContent;

beforeEach(function () {
    openRestaurant();
    $this->table = DiningTable::factory()->create(['seats' => 4, 'min_guests' => 2]);
    $this->service = fn () => app(AvailabilityService::class);
});

it('будує слоти від відкриття до останнього перед закриттям', function () {
    $slots = ($this->service)()->slots(kyiv('2026-11-17'), 2);

    // 11:00–23:00, останній слот за 90 хв до закриття
    expect($slots[0]['time'])->toBe('11:00')
        ->and(end($slots)['time'])->toBe('21:30')
        ->and($slots)->toHaveCount(22)
        ->and(collect($slots)->every(fn ($slot) => $slot['available']))->toBeTrue();
});

it('не пропонує слоти ближче за мінімальний час до візиту', function () {
    $this->travelTo(kyiv('2026-11-16 17:10'));

    $slots = ($this->service)()->slots(kyiv('2026-11-16'), 2);

    expect($slots[0]['time'])->toBe('18:30');
});

it('не дає бронювати далі за горизонт і в минулому', function () {
    expect(($this->service)()->isBookableDate(kyiv('2026-12-16')))->toBeTrue()
        ->and(($this->service)()->isBookableDate(kyiv('2026-12-17')))->toBeFalse()
        ->and(($this->service)()->isBookableDate(kyiv('2026-11-15')))->toBeFalse();
});

it('враховує вихідний день тижня й особливі дні', function () {
    WorkingHour::where('weekday', 2)->update(['is_closed' => true]);
    SpecialDay::create(['date' => '2026-11-18', 'is_closed' => true]);
    SpecialDay::create(['date' => '2026-11-19', 'is_closed' => false, 'opens_at' => '14:00', 'closes_at' => '18:00']);
    SiteContent::flush();

    expect(($this->service)()->slotTimes(kyiv('2026-11-17')))->toBe([])
        ->and(($this->service)()->slotTimes(kyiv('2026-11-18')))->toBe([])
        ->and(collect(($this->service)()->slotTimes(kyiv('2026-11-19')))->map->format('H:i')->all())
        ->toBe(['14:00', '14:30', '15:00', '15:30', '16:00', '16:30']);
});

it('розуміє закриття після півночі', function () {
    // П'ятниця: 11:00–00:00
    $slots = ($this->service)()->slots(kyiv('2026-11-20'), 2);

    expect(end($slots)['time'])->toBe('22:30')
        ->and(($this->service)()->endsAt(kyiv('2026-11-20 22:30'))->format('Y-m-d H:i'))->toBe('2026-11-21 00:00');
});

it('обрізає кінець бронювання часом закриття', function () {
    expect(($this->service)()->endsAt(kyiv('2026-11-17 21:30'))->format('H:i'))->toBe('23:00')
        ->and(($this->service)()->endsAt(kyiv('2026-11-17 18:00'))->format('H:i'))->toBe('20:00');
});

it('вважає столик зайнятим із урахуванням перерви між бронюваннями', function () {
    Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:00'))->create();

    $state = fn (string $time) => ($this->service)()->tableStates(kyiv("2026-11-17 {$time}"), 2)[$this->table->id];

    // Бронювання 18:00–20:00, перерва 15 хв: 16:00–18:00 і 20:00–22:00 упритул не проходять
    expect($state('15:30'))->toBe(AvailabilityService::STATE_FREE)
        ->and($state('16:00'))->toBe(AvailabilityService::STATE_BUSY)
        ->and($state('19:00'))->toBe(AvailabilityService::STATE_BUSY)
        ->and($state('20:00'))->toBe(AvailabilityService::STATE_BUSY)
        ->and($state('20:30'))->toBe(AvailabilityService::STATE_FREE);
});

it('не враховує скасовані, завершені й прострочені бронювання', function (ReservationStatus $status, string $expected) {
    Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:00'))->status($status)->create();

    expect(($this->service)()->tableStates(kyiv('2026-11-17 18:00'), 2)[$this->table->id])->toBe($expected);
})->with([
    [ReservationStatus::PendingPayment, AvailabilityService::STATE_BUSY],
    [ReservationStatus::Confirmed, AvailabilityService::STATE_BUSY],
    [ReservationStatus::Seated, AvailabilityService::STATE_BUSY],
    [ReservationStatus::Completed, AvailabilityService::STATE_FREE],
    [ReservationStatus::Cancelled, AvailabilityService::STATE_FREE],
    [ReservationStatus::NoShow, AvailabilityService::STATE_FREE],
    [ReservationStatus::Expired, AvailabilityService::STATE_FREE],
]);

it('перевіряє місткість столика', function () {
    $states = fn (int $guests) => ($this->service)()->tableStates(kyiv('2026-11-17 18:00'), $guests)[$this->table->id];

    expect($states(1))->toBe(AvailabilityService::STATE_UNFIT)
        ->and($states(2))->toBe(AvailabilityService::STATE_FREE)
        ->and($states(4))->toBe(AvailabilityService::STATE_FREE)
        ->and($states(5))->toBe(AvailabilityService::STATE_UNFIT);
});

it('позначає слот недоступним, коли зайняті всі відповідні столики', function () {
    Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:00'))->create();
    // Столик на двох для компанії з чотирьох не рахується
    DiningTable::factory()->create(['seats' => 2]);

    $slots = collect(($this->service)()->slots(kyiv('2026-11-17'), 4))->pluck('available', 'time');

    expect($slots['18:00'])->toBeFalse()
        ->and($slots['15:30'])->toBeTrue()
        ->and($slots['20:30'])->toBeTrue();
});

it('не показує вимкнені столики й столики вимкнених залів', function () {
    $off = DiningTable::factory()->create(['is_active' => false]);
    $hidden = DiningTable::factory()->create();
    $hidden->hall->update(['is_active' => false]);

    $states = ($this->service)()->tableStates(kyiv('2026-11-17 18:00'), 2);

    expect($states)->toHaveKey($this->table->id)
        ->not->toHaveKey($off->id)
        ->not->toHaveKey($hidden->id);
});

it('радить найменший столик, що підходить', function () {
    $small = DiningTable::factory()->create(['seats' => 2, 'min_guests' => 1]);
    DiningTable::factory()->create(['seats' => 8, 'min_guests' => 1]);

    expect(($this->service)()->suggestTable(kyiv('2026-11-17 18:00'), 2)->id)->toBe($small->id)
        ->and(($this->service)()->suggestTable(kyiv('2026-11-17 18:00'), 3)->id)->toBe($this->table->id);
});

it('показує столик, який утримує інший гість, зайнятим', function () {
    $start = kyiv('2026-11-17 18:00');
    app(TableHold::class)->put('guest-a', $this->table->id, $start, $start->addHours(2));

    expect(($this->service)()->tableStates($start, 2, 'guest-b')[$this->table->id])->toBe(AvailabilityService::STATE_BUSY)
        ->and(($this->service)()->tableStates($start, 2, 'guest-a')[$this->table->id])->toBe(AvailabilityService::STATE_FREE);

    // Утримання діє обмежений час
    $this->travel(11)->minutes();

    expect(($this->service)()->tableStates($start, 2, 'guest-b')[$this->table->id])->toBe(AvailabilityService::STATE_FREE);
});
