<?php

use App\Actions\Orders\AddOrderItem;
use App\Actions\Orders\CreatePreorder;
use App\Actions\Orders\OpenOrder;
use App\Actions\Payments\RefundPayment;
use App\Actions\Payments\StartReservationPayment;
use App\Actions\Reservations\CancelReservation;
use App\Actions\Reservations\ConfirmReservation;
use App\Actions\Reservations\CreateReservation;
use App\Actions\Reservations\MarkReservationNoShow;
use App\Actions\Reservations\MoveReservation;
use App\Actions\Reservations\ReservationData;
use App\Actions\Reservations\SeatReservation;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exceptions\Orders\OrderException;
use App\Exceptions\Payments\PaymentException;
use App\Mail\PreorderPaidMail;
use App\Mail\ReservationCancelledMail;
use App\Mail\ReservationConfirmedMail;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\User;
use App\Services\Booking\BookingSettings;
use App\Services\Booking\RefundPolicy;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    openRestaurant();
    Mail::fake();
    $this->table = DiningTable::factory()->create(['number' => '7', 'seats' => 4, 'min_guests' => 1]);
    $this->soup = Dish::factory()->create(['price' => 12000, 'cook_time_minutes' => 20]);
    $this->steak = Dish::factory()->create(['price' => 45000, 'cook_time_minutes' => 35]);

    // Візит — завтра о 18:00, тобто за 33 години від «зараз»
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

    $this->book = fn (array $overrides = []) => app(CreateReservation::class)->handle(($this->data)($overrides));
    $this->preorder = fn () => [$this->soup->id => 2, $this->steak->id => 1];
});

// ---------- Платне бронювання (частина 2, п. 3.6) ----------

it('типово бронювання безкоштовне', function () {
    expect(BookingSettings::fee())->toBe(0)
        ->and(($this->book)()->status)->toBe(ReservationStatus::Confirmed);
});

it('з увімкненою платою створює бронювання, яке чекає оплати й тримає столик 15 хвилин', function () {
    enableBookingFee();

    $reservation = ($this->book)();

    expect($reservation->status)->toBe(ReservationStatus::PendingPayment)
        ->and($reservation->fee_amount)->toBe(20000)
        ->and($reservation->expires_at->equalTo(now()->addMinutes(15)))->toBeTrue()
        ->and($reservation->feeState())->toBe('pending');

    // Лист про підтвердження піде лише після оплати
    Mail::assertNothingQueued();

    // Столик зайнятий: другий гість на цей час його не отримає
    expect(fn () => ($this->book)(['name' => 'Другий гість']))->toThrow(DomainException::class);
});

it('підтверджує платне бронювання після оплати плати', function () {
    enableBookingFee();
    $reservation = ($this->book)();

    $payment = app(StartReservationPayment::class)->handle($reservation, 'en');

    expect($payment->reservation_id)->toBe($reservation->id)
        ->and($payment->order_id)->toBeNull()
        ->and($payment->amount)->toBe(0)
        ->and($payment->booking_fee_amount)->toBe(20000)
        ->and($payment->tip_amount)->toBe(0)
        ->and($payment->total())->toBe(20000)
        ->and($payment->waiter_id)->toBeNull()
        ->and($payment->locale())->toBe('en')
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::PendingPayment);

    settle($payment);
    $reservation->refresh();

    expect($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->expires_at)->toBeNull()
        ->and($reservation->feeState())->toBe('paid');

    Mail::assertQueued(ReservationConfirmedMail::class, fn (ReservationConfirmedMail $mail) => $mail->payment?->is($payment));
});

it('повторне відкриття оплати повертає той самий рахунок', function () {
    enableBookingFee();
    $reservation = ($this->book)();

    $first = app(StartReservationPayment::class)->handle($reservation);
    $second = app(StartReservationPayment::class)->handle($reservation);

    expect($second->id)->toBe($first->id)
        ->and(Payment::count())->toBe(1);
});

it('відхилений платіж лишає бронювання неоплаченим, і гість може спробувати ще раз', function () {
    enableBookingFee();
    $reservation = ($this->book)();

    $failed = settle(app(StartReservationPayment::class)->handle($reservation), PaymentStatus::Failure);

    expect($failed->status)->toBe(PaymentStatus::Failure)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::PendingPayment);

    payReservation($reservation);

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

it('не бере плату з бронювань, які персонал створює в адмінці', function (ReservationSource $source) {
    enableBookingFee();
    $manager = User::factory()->manager()->create();

    $reservation = ($this->book)(['source' => $source, 'createdBy' => $manager->id]);

    expect($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->fee_amount)->toBe(0)
        ->and($reservation->feeState())->toBeNull();
})->with([ReservationSource::Admin, ReservationSource::Phone]);

it('зміна суми плати не зачіпає вже створені бронювання', function () {
    enableBookingFee(20000);
    $old = ($this->book)();

    enableBookingFee(50000);
    $new = ($this->book)(['startsAt' => kyiv('2026-11-18 18:00')]);

    expect($old->fresh()->fee_amount)->toBe(20000)
        ->and(app(StartReservationPayment::class)->handle($old)->booking_fee_amount)->toBe(20000)
        ->and($new->fee_amount)->toBe(50000);
});

it('неоплачене бронювання через 15 хвилин звільняє столик', function () {
    enableBookingFee();
    $reservation = ($this->book)();
    $payment = app(StartReservationPayment::class)->handle($reservation);

    $this->travel(10)->minutes();
    $this->artisan('reservations:expire-unpaid')->assertSuccessful();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::PendingPayment);

    $this->travel(6)->minutes();
    $this->artisan('reservations:expire-unpaid')->assertSuccessful();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Expired)
        ->and($reservation->fresh()->feeState())->toBe('unpaid')
        // Рахунок у провайдера погашено: сплатити звільнений столик уже не можна
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Expired);

    // Столик знову вільний для інших гостей
    expect(($this->book)(['name' => 'Інший гість'])->status)->toBe(ReservationStatus::PendingPayment);
});

it('не дає почати оплату простроченого бронювання', function () {
    enableBookingFee();
    $reservation = ($this->book)();

    $this->travel(16)->minutes();

    expect(fn () => app(StartReservationPayment::class)->handle($reservation))
        ->toThrow(PaymentException::class, __('payment.errors.reservation_expired'));
});

it('повертає кошти, якщо оплата надійшла за вже прострочене бронювання', function () {
    enableBookingFee();
    $reservation = ($this->book)();
    $payment = app(StartReservationPayment::class)->handle($reservation);

    // Рахунок не встигли погасити, а столик уже звільнено
    $reservation->transitionTo(ReservationStatus::Expired);
    settle($payment);

    expect($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->refunded_amount)->toBe(20000)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Expired);

    Mail::assertNotQueued(ReservationConfirmedMail::class);
});

it('менеджер може підтвердити неоплачене бронювання без оплати', function () {
    enableBookingFee();
    $reservation = ($this->book)(['preorderItems' => ($this->preorder)()]);
    $payment = app(StartReservationPayment::class)->handle($reservation);

    app(ConfirmReservation::class)->handle($reservation);

    expect($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->fee_amount)->toBe(0)
        ->and($reservation->expires_at)->toBeNull()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Expired)
        // Неоплачене попереднє замовлення кухня не готує
        ->and($reservation->orders()->sole()->status)->toBe(OrderStatus::Cancelled);

    Mail::assertQueued(ReservationConfirmedMail::class);
});

// ---------- Попереднє замовлення (частина 2, п. 4.4) ----------

it('створює попереднє замовлення разом із бронюванням', function () {
    $reservation = ($this->book)(['preorderItems' => ($this->preorder)()]);
    $order = $reservation->preorder;

    expect($reservation->status)->toBe(ReservationStatus::PendingPayment)
        ->and($reservation->fee_amount)->toBe(0)
        ->and($order->type)->toBe(OrderType::Preorder)
        ->and($order->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($order->table_id)->toBe($this->table->id)
        ->and($order->serve_at->equalTo($reservation->starts_at))->toBeTrue()
        ->and($order->subtotal)->toBe(69000)
        ->and($order->items)->toHaveCount(2)
        // Неоплачене попереднє замовлення столик не займає
        ->and($this->table->openOrder())->toBeNull();
});

it('оплата попереднього замовлення підтверджує бронювання й відкриває замовлення для кухні', function () {
    $reservation = ($this->book)(['preorderItems' => ($this->preorder)()]);

    $payment = app(StartReservationPayment::class)->handle($reservation);

    expect($payment->amount)->toBe(69000)
        ->and($payment->booking_fee_amount)->toBe(0)
        ->and($payment->order_id)->toBe($reservation->preorder->id);

    settle($payment);
    $order = $reservation->preorder()->first();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($order->status)->toBe(OrderStatus::Open)
        ->and($order->paid_total)->toBe(69000)
        ->and($order->amountDue())->toBe(0)
        // Оплачене замовлення на завтра сьогоднішній столик не займає
        ->and($this->table->openOrder())->toBeNull();
});

it('платне бронювання з попереднім замовленням оплачується одним платежем', function () {
    enableBookingFee();
    $reservation = ($this->book)(['preorderItems' => ($this->preorder)()]);

    $payment = app(StartReservationPayment::class)->handle($reservation);

    expect(Payment::count())->toBe(1)
        ->and($payment->amount)->toBe(69000)
        ->and($payment->booking_fee_amount)->toBe(20000)
        ->and($payment->total())->toBe(89000);

    settle($payment);

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->fresh()->feeState())->toBe('paid')
        // Плата за бронювання в сплачене за стравами не входить (частина 2, п. 2.3)
        ->and($reservation->preorder()->first()->paid_total)->toBe(69000);
});

it('приймає в попереднє замовлення лише дозволені страви, які є в наявності', function (array $state) {
    $dish = Dish::factory()->create($state);

    expect(fn () => ($this->book)(['preorderItems' => [$dish->id => 1]]))
        ->toThrow(OrderException::class, __('booking.preorder.errors.dish_unavailable'));

    // Бронювання без замовлення теж не лишилося: усе створюється однією транзакцією
    expect(Reservation::count())->toBe(0);
})->with([
    'не для попереднього замовлення' => [['is_preorderable' => false]],
    'немає в наявності' => [['is_available' => false]],
]);

it('не приймає попереднє замовлення менш ніж за три години до візиту', function () {
    // «Зараз» 09:00, візит об 11:30 — за дві з половиною години
    expect(fn () => ($this->book)(['startsAt' => kyiv('2026-11-16 11:30'), 'preorderItems' => ($this->preorder)()]))
        ->toThrow(OrderException::class, __('booking.preorder.errors.too_late'));

    expect(Reservation::count())->toBe(0);
});

it('прострочене бронювання скасовує своє попереднє замовлення', function () {
    $reservation = ($this->book)(['preorderItems' => ($this->preorder)()]);

    $this->travel(16)->minutes();
    $this->artisan('reservations:expire-unpaid');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Expired)
        ->and($reservation->orders()->sole()->status)->toBe(OrderStatus::Cancelled);
});

it('додає попереднє замовлення до підтвердженого бронювання без повторної плати', function () {
    enableBookingFee();
    $reservation = ($this->book)();
    payReservation($reservation);

    app(CreatePreorder::class)->handle($reservation, ($this->preorder)());
    $payment = app(StartReservationPayment::class)->handle($reservation);

    expect($payment->amount)->toBe(69000)
        ->and($payment->booking_fee_amount)->toBe(0);

    settle($payment);

    expect($reservation->preorder()->first()->status)->toBe(OrderStatus::Open)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);

    Mail::assertQueued(PreorderPaidMail::class);

    // Друге попереднє замовлення до того самого бронювання не приймається
    expect(fn () => app(CreatePreorder::class)->handle($reservation, ($this->preorder)()))
        ->toThrow(OrderException::class, __('booking.preorder.errors.exists'));
});

it('неоплачене попереднє замовлення до підтвердженого бронювання скасовується, а бронювання лишається', function () {
    $reservation = ($this->book)();
    $order = app(CreatePreorder::class)->handle($reservation, ($this->preorder)());
    $payment = app(StartReservationPayment::class)->handle($reservation);

    $this->travel(16)->minutes();
    $this->artisan('reservations:expire-unpaid');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

it('після посадки попереднє замовлення стає замовленням столика', function () {
    $waiter = User::factory()->waiter()->create();
    $reservation = ($this->book)(['preorderItems' => ($this->preorder)()]);
    payReservation($reservation);
    $order = $reservation->preorder()->first();

    $this->travelTo(kyiv('2026-11-17 18:00'));
    app(SeatReservation::class)->handle($reservation->fresh(), $waiter);
    $order->refresh();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Seated)
        ->and($order->type)->toBe(OrderType::DineIn)
        ->and($order->waiter_id)->toBe($waiter->id)
        ->and($this->table->openOrder()->id)->toBe($order->id)
        // Нового замовлення посадка не відкрила: усе дозамовлене піде в це саме
        ->and($reservation->orders()->count())->toBe(1)
        ->and($reservation->preorder()->first()->id)->toBe($order->id);

    app(AddOrderItem::class)->handle($order, Dish::factory()->create(['price' => 9000]), 1, null, $waiter);

    // Сплачене наперед уже враховано: до сплати лишається тільки дозамовлене
    expect($order->fresh()->amountDue())->toBe(9000);
});

it('не садить гостей із попереднім замовленням, поки на столику відкрите чуже замовлення', function () {
    $reservation = ($this->book)(['preorderItems' => ($this->preorder)()]);
    payReservation($reservation);

    $this->travelTo(kyiv('2026-11-17 18:00'));
    app(OpenOrder::class)->handle($this->table);

    expect(fn () => app(SeatReservation::class)->handle($reservation->fresh()))->toThrow(OrderException::class);
    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

it('перенесення бронювання переносить і попереднє замовлення', function () {
    $other = DiningTable::factory()->create(['seats' => 4, 'min_guests' => 1]);
    $reservation = ($this->book)(['preorderItems' => ($this->preorder)()]);
    payReservation($reservation);

    app(MoveReservation::class)->handle($reservation->fresh(), $other->id, kyiv('2026-11-17 19:30'), 2);
    $order = $reservation->preorder()->first();

    expect($order->table_id)->toBe($other->id)
        ->and($order->serve_at->format('Y-m-d H:i'))->toBe('2026-11-17 17:30');
});

// ---------- Скасування й повернення (частина 2, пп. 3.5, 3.6) ----------

it('гість скасував завчасно — повертається все сплачене', function () {
    enableBookingFee();
    $reservation = ($this->book)(['preorderItems' => ($this->preorder)()]);
    $payment = payReservation($reservation);

    expect(RefundPolicy::summary($reservation->fresh()))->toBe(['paid' => 89000, 'refund' => 89000]);

    app(CancelReservation::class)->handle($reservation->fresh());
    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->refunded_amount)->toBe(89000)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($reservation->fresh()->feeState())->toBe('refunded')
        ->and($reservation->orders()->sole()->status)->toBe(OrderStatus::Cancelled)
        ->and($reservation->orders()->sole()->items()->where('status', '!=', OrderItemStatus::Cancelled)->count())->toBe(0);

    Mail::assertQueued(ReservationCancelledMail::class, fn (ReservationCancelledMail $mail) => $mail->refunded === 89000);
});

it('гість скасував пізніше межі — сплачене не повертається', function () {
    enableBookingFee();
    $reservation = ($this->book)(['preorderItems' => ($this->preorder)()]);
    $payment = payReservation($reservation);

    // За три години до візиту: межа безкоштовного скасування (шість годин) уже позаду
    $this->travelTo(kyiv('2026-11-17 15:00'));

    expect(RefundPolicy::summary($reservation->fresh()))->toBe(['paid' => 89000, 'refund' => 0]);

    app(CancelReservation::class)->handle($reservation->fresh());
    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Success)
        ->and($payment->refunded_amount)->toBe(0)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($reservation->fresh()->feeState())->toBe('paid')
        ->and($reservation->orders()->sole()->status)->toBe(OrderStatus::Cancelled);

    Mail::assertQueued(ReservationCancelledMail::class, fn (ReservationCancelledMail $mail) => $mail->refunded === 0);
});

it('заклад скасував пізно — плата за бронювання повертається, а страви лишаються на рішення менеджера', function () {
    enableBookingFee();
    $manager = User::factory()->manager()->create();
    $reservation = ($this->book)(['preorderItems' => ($this->preorder)()]);
    $payment = payReservation($reservation);

    $this->travelTo(kyiv('2026-11-17 15:00'));
    app(CancelReservation::class)->handle($reservation->fresh(), 'Санітарний день', $manager);
    $payment->refresh();

    // Часткове повернення: платіж лишається успішним, росте лише повернена сума
    expect($payment->status)->toBe(PaymentStatus::Success)
        ->and($payment->refunded_amount)->toBe(20000)
        ->and($payment->refundedFee())->toBe(20000)
        ->and($payment->refundedOrderAmount())->toBe(0)
        ->and($payment->refundable())->toBe(69000)
        ->and($reservation->fresh()->feeState())->toBe('refunded');

    // Менеджер вирішив повернути й страви — тепер платіж повернено цілком
    app(RefundPayment::class)->handle($payment, $manager, 'Вибачення за скасування', RefundPayment::ORDER);

    expect($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->refunded_amount)->toBe(89000)
        ->and($reservation->orders()->sole()->paid_total)->toBe(0);
});

it('повертає з платежу лише суму страв, коли межа для плати вже минула, а для страв — ні', function () {
    Setting::put('booking', ['fee_enabled' => true, 'fee_amount' => 20000, 'fee_refund_until' => 2880, 'preorder_free_cancel' => 360]);
    $reservation = ($this->book)(['preorderItems' => ($this->preorder)()]);
    $payment = payReservation($reservation);

    // До візиту 33 години: плату (межа 48 годин) повертати пізно, страви (межа 6 годин) — ще можна
    app(CancelReservation::class)->handle($reservation->fresh());
    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Success)
        ->and($payment->refunded_amount)->toBe(69000)
        ->and($payment->refundedOrderAmount())->toBe(69000)
        ->and($payment->refundedFee())->toBe(0)
        ->and($reservation->orders()->sole()->paid_total)->toBe(0);
});

it('скасування неоплаченого бронювання гасить рахунок і нічого не повертає', function () {
    enableBookingFee();
    $reservation = ($this->book)();
    $payment = app(StartReservationPayment::class)->handle($reservation);

    app(CancelReservation::class)->handle($reservation);

    expect($reservation->status)->toBe(ReservationStatus::Cancelled)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Expired);
});

it('неявка не повертає плату й скасовує попереднє замовлення', function () {
    enableBookingFee();
    $reservation = ($this->book)(['preorderItems' => ($this->preorder)()]);
    $payment = payReservation($reservation);

    $this->travelTo(kyiv('2026-11-17 18:40'));
    app(MarkReservationNoShow::class)->handle($reservation->fresh());

    expect($reservation->fresh()->status)->toBe(ReservationStatus::NoShow)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Success)
        ->and($payment->fresh()->refunded_amount)->toBe(0)
        ->and($reservation->orders()->sole()->status)->toBe(OrderStatus::Cancelled);
});
