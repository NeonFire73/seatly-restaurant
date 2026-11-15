<?php

use App\Actions\Orders\CreatePreorder;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Filament\Pages\BookingSettingsPage;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Reservations\Pages\ListReservations;
use App\Filament\Resources\Reservations\Pages\ViewReservation;
use App\Filament\Resources\Reservations\ReservationActions;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Booking\BookingSettings;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

beforeEach(function () {
    openRestaurant();
    Mail::fake();
    $this->owner = User::factory()->owner()->create();
    $this->manager = User::factory()->manager()->create();
    $this->table = DiningTable::factory()->create(['number' => '7', 'seats' => 4, 'min_guests' => 1]);

    // Неоплачене платне бронювання з сайту на завтра, 18:00
    $this->pending = fn () => Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:00'))
        ->status(ReservationStatus::PendingPayment)
        ->create(['fee_amount' => 20000, 'expires_at' => now()->addMinutes(15)]);
});

// ---------- Сторінка «Налаштування» (частина 2, пп. 3.6 і 7.9) ----------

it('відкриває налаштування платного бронювання лише власникові', function () {
    foreach (UserRole::cases() as $role) {
        $response = $this->actingAs(User::factory()->role($role)->create())->get('/admin/settings');

        if ($role === UserRole::Owner) {
            $response->assertOk()->assertSee('Платне бронювання');
        } else {
            $response->assertForbidden();
        }
    }
});

it('власник вмикає платне бронювання й задає суму', function () {
    $this->actingAs($this->owner);

    livewire(BookingSettingsPage::class)
        ->assertFormSet(['fee_enabled' => false, 'fee_refund_hours' => 6, 'preorder_min_lead_hours' => 3, 'payment_hold' => 15])
        ->fillForm(['fee_enabled' => true, 'fee_amount' => '250.50', 'fee_refund_hours' => 12])
        ->callAction('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Налаштування збережено');

    expect(BookingSettings::fee())->toBe(25050)
        ->and(BookingSettings::feeRefundUntil())->toBe(720)
        // Решта параметрів не загубилася
        ->and(BookingSettings::preorderMinLead())->toBe(180)
        ->and(BookingSettings::paymentHold())->toBe(15);

    // Вимкнена плата суму зберігає, але бронювання знову безкоштовне
    livewire(BookingSettingsPage::class)
        ->assertFormSet(['fee_enabled' => true, 'fee_amount' => '250.50'])
        ->fillForm(['fee_enabled' => false])
        ->callAction('save');

    expect(BookingSettings::fee())->toBe(0);
});

it('не дає ввімкнути платне бронювання з нульовою чи некоректною сумою', function (string $amount) {
    $this->actingAs($this->owner);

    livewire(BookingSettingsPage::class)
        ->fillForm(['fee_enabled' => true, 'fee_amount' => $amount])
        ->callAction('save')
        ->assertHasFormErrors(['fee_amount'], 'form');

    expect(BookingSettings::fee())->toBe(0);
})->with(['0', '', 'двісті', '-5']);

it('відхиляє спробу менеджера змінити плату за бронювання', function () {
    $this->actingAs($this->manager);

    livewire(BookingSettingsPage::class)->assertForbidden();

    expect($this->manager->can('manage-booking-payments'))->toBeFalse()
        ->and($this->owner->can('manage-booking-payments'))->toBeTrue()
        ->and(BookingSettings::fee())->toBe(0);
});

// ---------- Бронювання й платежі в адмінці (частина 2, пп. 7.4 і 7.7) ----------

it('показує в бронюванні плату, її стан і попереднє замовлення', function () {
    $reservation = ($this->pending)();
    app(CreatePreorder::class)->handle($reservation, [Dish::factory()->create(['name' => ['uk' => 'Борщ'], 'price' => 12000])->id => 2]);
    $this->actingAs($this->manager);

    livewire(ListReservations::class, ['activeTab' => 'upcoming'])
        ->assertSee('очікує оплати')
        ->assertSee('240 ₴');

    livewire(ViewReservation::class, ['record' => $reservation->getRouteKey()])
        ->assertSee('Плата за бронювання')
        ->assertSee('200 ₴')
        ->assertSee('очікує оплати')
        ->assertSee('Борщ × 2');

    payReservation($reservation);

    livewire(ViewReservation::class, ['record' => $reservation->getRouteKey()])
        ->assertSee('сплачено')
        ->assertActionHidden('confirm');
});

it('менеджер підтверджує неоплачене бронювання без оплати', function () {
    $reservation = ($this->pending)();
    $this->actingAs($this->manager);

    livewire(ViewReservation::class, ['record' => $reservation->getRouteKey()])
        ->assertActionVisible('confirm')
        ->callAction('confirm')
        ->assertNotified('Бронювання підтверджено');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->fresh()->fee_amount)->toBe(0);
});

it('офіціант не може підтвердити неоплачене бронювання', function () {
    $reservation = ($this->pending)();
    $this->actingAs(User::factory()->waiter()->create());

    livewire(ViewReservation::class, ['record' => $reservation->getRouteKey()])->assertActionHidden('confirm');
});

it('скасування в адмінці попереджає про повернення й повертає плату', function () {
    $reservation = ($this->pending)();
    $payment = payReservation($reservation);
    $this->actingAs($this->manager);

    expect(ReservationActions::cancelDescription($reservation->fresh()))->toContain('Сплачені 200 ₴ повернуться гостеві автоматично');

    livewire(ViewReservation::class, ['record' => $reservation->getRouteKey()])
        ->callAction('cancel', ['reason' => 'Санітарний день']);

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});

it('менеджер повертає з платежу лише плату за бронювання', function () {
    $reservation = ($this->pending)();
    app(CreatePreorder::class)->handle($reservation, [Dish::factory()->create(['price' => 30000])->id => 1]);
    $payment = payReservation($reservation);
    $this->actingAs($this->manager);

    livewire(ViewPayment::class, ['record' => $payment->getRouteKey()])
        ->assertSee($reservation->code)
        ->assertSee('Плата за бронювання')
        ->callAction('refund', ['reason' => 'Гість попросив', 'part' => 'fee'])
        ->assertNotified('Кошти повернено')
        // Страви ще можна повернути
        ->assertActionVisible('refund');

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Success)
        ->and($payment->refunded_amount)->toBe(20000)
        ->and($payment->refundedFee())->toBe(20000)
        ->and($reservation->preorder()->first()->paid_total)->toBe(30000);

    livewire(ViewPayment::class, ['record' => $payment->getRouteKey()])
        ->callAction('refund', ['reason' => 'І страви теж'])
        ->assertActionHidden('refund');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});
