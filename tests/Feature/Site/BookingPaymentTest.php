<?php

use App\Actions\Payments\StartReservationPayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Livewire\BookingWizard;
use App\Livewire\PaymentResult;
use App\Livewire\ReservationPage;
use App\Mail\ReservationConfirmedMail;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Hall;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    openRestaurant();
    Mail::fake();
    $this->hall = Hall::factory()->create(['name' => ['uk' => 'Основний зал', 'en' => 'Main hall']]);
    $this->table = DiningTable::factory()->for($this->hall)->create(['number' => '7', 'seats' => 4, 'min_guests' => 1]);
    $this->soup = Dish::factory()->create(['name' => ['uk' => 'Борщ', 'en' => 'Borscht'], 'price' => 12000]);
    $this->steak = Dish::factory()->create(['name' => ['uk' => 'Стейк', 'en' => 'Steak'], 'price' => 45000]);

    // Майстер, доведений до кроку попереднього замовлення; час візиту — завтра о 18:00
    $this->atPreorder = fn (string $time = '18:00', string $date = '2026-11-17') => Livewire::test(BookingWizard::class)
        ->call('selectDate', $date)
        ->call('selectTime', $time)
        ->call('chooseTable')
        ->call('selectTable', $this->table->id)
        ->call('enterContacts')
        ->set('name', 'Оксана Гриценко')
        ->set('phone', '+380 67 123 45 67')
        ->set('email', 'oksana@example.com')
        ->set('consent', true)
        ->call('review')
        ->assertSet('step', 4);
});

// ---------- Майстер бронювання (частина 1, п. 5.3) ----------

it('показує плату за бронювання вже на першому кроці й лише коли вона ввімкнена', function () {
    $this->get('/booking')->assertOk()->assertDontSee('Бронювання платне');

    enableBookingFee(20000);

    $this->get('/booking')->assertOk()->assertSee('Бронювання платне: 200 ₴');
    $this->get('/en/booking')->assertOk()->assertSee('A booking fee applies: ₴200');
});

it('веде гостя з платним бронюванням на платіжну сторінку, а екран успіху показує після оплати', function () {
    enableBookingFee(20000);

    $component = ($this->atPreorder)()
        ->call('toSummary')
        ->assertSet('step', 5)
        ->assertSee(__('booking.fee.label'))
        ->assertSee(__('booking.actions.book_and_pay'))
        ->assertDontSee(__('booking.summary.free'))
        ->call('submit');

    $reservation = Reservation::sole();
    $payment = Payment::sole();

    $component->assertRedirect($payment->page_url);

    expect($reservation->status)->toBe(ReservationStatus::PendingPayment)
        ->and($payment->booking_fee_amount)->toBe(20000)
        // Майстер скинуто: повернувшись, гість не побачить старих даних
        ->and($component->get('doneToken'))->toBeNull();

    Mail::assertNothingQueued();

    $this->get('/fake-pay/'.$payment->id)->assertOk()->assertSee($reservation->code)->assertSee('200 ₴');
    $this->post('/fake-pay/'.$payment->id, ['action' => 'success'])->assertRedirect('/pay/'.$payment->id.'/return');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);

    Livewire::test(PaymentResult::class, ['paymentId' => $payment->id])
        ->assertSee(__('payment.result.success_title'))
        ->assertSee(__('payment.result.success_text_reservation'))
        ->assertSee($reservation->code)
        ->assertSee(__('booking.fee.label'))
        ->assertSee(locale_route('reservation.show', ['token' => $reservation->token]));

    $this->get('/pay/'.$payment->id.'/receipt')->assertOk()->assertSee($reservation->code)->assertSee(__('booking.fee.label'));
    Mail::assertQueued(ReservationConfirmedMail::class);
});

it('збирає попереднє замовлення в кошик і оплачує його разом із бронюванням', function () {
    $component = ($this->atPreorder)()
        ->assertSee(__('booking.preorder.with_dishes'))
        ->call('choosePreorder', true)
        ->assertSee('Борщ')
        ->call('addDish', $this->soup->id)
        ->call('addDish', $this->soup->id)
        ->call('addDish', $this->steak->id)
        ->call('addDish', $this->steak->id)
        ->call('removeDish', $this->steak->id)
        ->assertSet('cart', [$this->soup->id => 2, $this->steak->id => 1])
        ->call('toSummary')
        ->assertSet('step', 5)
        ->assertSee('Борщ × 2')
        ->assertSee('690 ₴')
        ->call('submit');

    $reservation = Reservation::sole();
    $payment = Payment::sole();

    $component->assertRedirect($payment->page_url);

    expect($reservation->status)->toBe(ReservationStatus::PendingPayment)
        ->and($reservation->preorder->subtotal)->toBe(69000)
        ->and($payment->amount)->toBe(69000)
        ->and($payment->order_id)->toBe($reservation->preorder->id);
});

it('не пускає до підтвердження з порожнім кошиком', function () {
    ($this->atPreorder)()
        ->call('choosePreorder', true)
        ->call('toSummary')
        ->assertSet('step', 4)
        ->assertSee(__('booking.preorder.errors.empty'))
        ->call('choosePreorder', false)
        ->call('toSummary')
        ->assertSet('step', 5);
});

it('не додає в кошик страву, недоступну для попереднього замовлення', function () {
    $wine = Dish::factory()->create(['is_preorderable' => false]);

    ($this->atPreorder)()
        ->call('choosePreorder', true)
        ->call('addDish', $wine->id)
        ->call('addDish', 999999)
        ->assertSet('cart', []);
});

it('не пропонує попереднє замовлення, коли до візиту менш ніж три години', function () {
    // «Зараз» 09:00, візит об 11:30
    ($this->atPreorder)('11:30', '2026-11-16')
        ->assertDontSee(__('booking.preorder.table_only'))
        ->assertSee('Попереднє замовлення приймаємо не пізніше ніж за 3 години до візиту')
        ->call('choosePreorder', true)
        ->assertSet('wantsPreorder', false)
        ->call('toSummary')
        ->assertSet('step', 5);
});

it('повертає до кошика, якщо страву зняли з меню перед самим бронюванням', function () {
    $component = ($this->atPreorder)()
        ->call('choosePreorder', true)
        ->call('addDish', $this->soup->id)
        ->call('toSummary');

    $this->soup->update(['is_available' => false]);

    $component->call('submit')
        ->assertSet('step', 4)
        ->assertSet('cart', [])
        ->assertSee(__('booking.preorder.errors.dish_unavailable'));

    expect(Reservation::count())->toBe(0);
});

// ---------- Сторінка бронювання гостя (частина 1, п. 5.4) ----------

it('показує неоплаченому бронюванню кнопку оплати й веде на платіжну сторінку', function () {
    enableBookingFee(20000);
    $reservation = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:00'))
        ->status(ReservationStatus::PendingPayment)
        ->create(['fee_amount' => 20000, 'expires_at' => now()->addMinutes(15)]);

    $component = Livewire::test(ReservationPage::class, ['token' => $reservation->token])
        ->assertSee(__('booking.status.pending_payment'))
        ->assertSee(__('booking.page.pending_text'))
        ->assertSee(__('booking.page.pay', ['amount' => '200 ₴']))
        ->assertSee(__('booking.fee.state.pending'))
        // Неоплачене бронювання в календар не додають
        ->assertDontSee(__('booking.page.calendar'))
        ->call('pay');

    $component->assertRedirect(Payment::sole()->page_url);
});

it('після оплати показує плату сплаченою, а після скасування — поверненою', function () {
    enableBookingFee(20000);
    $reservation = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:00'))
        ->status(ReservationStatus::PendingPayment)
        ->create(['fee_amount' => 20000, 'expires_at' => now()->addMinutes(15)]);
    $payment = payReservation($reservation);

    Livewire::test(ReservationPage::class, ['token' => $reservation->token])
        ->assertSee(__('booking.status.confirmed'))
        ->assertSee(__('booking.fee.state.paid'))
        ->assertDontSee(__('booking.page.pending_text'))
        // До візиту більше шести годин: гість бачить, що гроші повернуться
        ->assertSee(__('booking.page.cancel_refund_full', ['amount' => '200 ₴']))
        ->call('cancel')
        ->assertSee(__('booking.page.cancelled'))
        ->assertSee(__('booking.fee.state.refunded'));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});

it('попереджає, що пізнє скасування коштів не повертає', function () {
    enableBookingFee(20000);
    $reservation = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:00'))
        ->status(ReservationStatus::PendingPayment)
        ->create(['fee_amount' => 20000, 'expires_at' => now()->addMinutes(15)]);
    payReservation($reservation);

    $this->travelTo(kyiv('2026-11-17 15:00'));

    Livewire::test(ReservationPage::class, ['token' => $reservation->token])
        ->assertSee(__('booking.page.cancel_refund_none', ['paid' => '200 ₴']));
});

it('дає додати попереднє замовлення до підтвердженого бронювання й оплатити його', function () {
    $reservation = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:00'))->create();

    $component = Livewire::test(ReservationPage::class, ['token' => $reservation->token])
        ->assertSee(__('booking.preorder.start'))
        ->call('startPreorder')
        ->assertSet('addingPreorder', true)
        ->assertSee('Борщ')
        ->call('submitPreorder')
        ->assertSee(__('booking.preorder.errors.empty'))
        ->call('addDish', $this->soup->id)
        ->call('submitPreorder');

    $payment = Payment::sole();
    $component->assertRedirect($payment->page_url);

    expect($payment->amount)->toBe(12000)
        ->and($payment->booking_fee_amount)->toBe(0)
        ->and($reservation->preorder->status)->toBe(OrderStatus::AwaitingPayment);

    // Гість повернувся, не заплативши: замовлення видно, є кнопки оплати й зміни
    Livewire::test(ReservationPage::class, ['token' => $reservation->token])
        ->assertSee('Борщ × 1')
        ->assertSee(__('booking.preorder.state.unpaid'))
        ->assertSee(__('booking.preorder.edit'))
        ->call('startPreorder')
        ->assertSet('cart', [$this->soup->id => 1]);

    settle($payment);

    Livewire::test(ReservationPage::class, ['token' => $reservation->token])
        ->assertSee(__('booking.preorder.state.paid'))
        ->assertSee(__('booking.preorder.serve_at', ['time' => '18:00']))
        ->assertDontSee(__('booking.preorder.start'))
        ->assertDontSee(__('booking.preorder.edit'));
});

it('не пропонує попереднє замовлення, коли до візиту лишилося замало часу', function () {
    $reservation = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-16 11:30'))->create();

    Livewire::test(ReservationPage::class, ['token' => $reservation->token])
        ->assertDontSee(__('booking.preorder.start'))
        ->call('startPreorder')
        ->assertSet('addingPreorder', false);
});

it('сторінка результату веде невдалу оплату бронювання назад на сторінку бронювання', function () {
    enableBookingFee(20000);
    $reservation = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:00'))
        ->status(ReservationStatus::PendingPayment)
        ->create(['fee_amount' => 20000, 'expires_at' => now()->addMinutes(15)]);
    $payment = settle(app(StartReservationPayment::class)->handle($reservation), PaymentStatus::Failure);

    Livewire::test(PaymentResult::class, ['paymentId' => $payment->id])
        ->assertSee(__('payment.result.failure_title'))
        ->assertSee(locale_route('reservation.show', ['token' => $reservation->token]));
});
