<?php

use App\Actions\Orders\AddOrderItem;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CloseOrder;
use App\Actions\Orders\OpenOrder;
use App\Actions\Orders\RecordOfflinePayment;
use App\Actions\Payments\ApplyPaymentStatus;
use App\Actions\Payments\HandlePaymentWebhook;
use App\Actions\Payments\RefundPayment;
use App\Actions\Payments\StartPayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\Orders\OrderException;
use App\Exceptions\Payments\BillChanged;
use App\Exceptions\Payments\PaymentException;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentStatusData;
use App\Services\Payments\TipSettings;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->table = DiningTable::factory()->create(['number' => '7']);
    $this->waiter = User::factory()->waiter()->create(['name' => 'Ірина Бондар']);
    $this->manager = User::factory()->manager()->create();
    $this->order = app(OpenOrder::class)->handle($this->table, $this->waiter);
    app(AddOrderItem::class)->handle($this->order, Dish::factory()->create(['price' => 20000]), 2);
});

/**
 * Те, що зробив би вебхук провайдера: новий стан рахунку з часом зміни.
 */
function webhook(Payment $payment, PaymentStatus $status, ?string $at = null): void
{
    $moment = CarbonImmutable::now();

    if ($at !== null) {
        $moment = CarbonImmutable::parse($at);
    }

    app(HandlePaymentWebhook::class)->handle(new PaymentStatusData((string) $payment->provider_invoice_id, $status, $moment));
}

it('створює платіж на суму до сплати з чайовими й офіціантом замовлення', function () {
    $payment = app(StartPayment::class)->handle($this->order, 4000, 40000, 'en');

    expect($payment->status)->toBe(PaymentStatus::Created)
        ->and($payment->method)->toBe(PaymentMethod::Online)
        ->and($payment->provider)->toBe(FakeGateway::NAME)
        ->and($payment->amount)->toBe(40000)
        ->and($payment->tip_amount)->toBe(4000)
        ->and($payment->total())->toBe(44000)
        ->and($payment->waiter_id)->toBe($this->waiter->id)
        ->and($payment->provider_invoice_id)->toStartWith('fake_')
        ->and($payment->page_url)->toContain('/fake-pay/'.$payment->id)
        ->and($payment->locale())->toBe('en')
        ->and($payment->expires_at->isFuture())->toBeTrue()
        // Неоплачений платіж рахунку не зменшує
        ->and($this->order->fresh()->amountDue())->toBe(40000);
});

it('після успішного вебхука зменшує суму до сплати, а повторний вебхук нічого не змінює', function () {
    $payment = app(StartPayment::class)->handle($this->order, 4000);

    webhook($payment, PaymentStatus::Processing, '2026-11-16 12:00:00');
    webhook($payment, PaymentStatus::Success, '2026-11-16 12:00:05');

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Success)
        ->and($payment->paid_at)->not->toBeNull()
        // Чайові в сплачене не входять (частина 2, п. 2.3)
        ->and($this->order->fresh()->paid_total)->toBe(40000)
        ->and($this->order->fresh()->amountDue())->toBe(0)
        ->and($this->waiter->notifications()->count())->toBe(1)
        ->and($this->manager->notifications()->count())->toBe(1);

    webhook($payment, PaymentStatus::Success, '2026-11-16 12:00:05');
    webhook($payment, PaymentStatus::Success, '2026-11-16 12:00:09');

    expect($this->order->fresh()->paid_total)->toBe(40000)
        ->and($payment->fresh()->history())->toHaveCount(3)
        ->and($this->waiter->notifications()->count())->toBe(1);

    app(CloseOrder::class)->handle($this->order);

    expect($this->order->status)->toBe(OrderStatus::Closed);
});

it('відкидає вебхуки, що прийшли не за порядком', function () {
    $payment = app(StartPayment::class)->handle($this->order);

    webhook($payment, PaymentStatus::Success, '2026-11-16 12:00:05');
    // Запізнілий «обробляється» не відкочує успішний платіж
    webhook($payment, PaymentStatus::Processing, '2026-11-16 12:00:01');
    webhook($payment, PaymentStatus::Created, '2026-11-16 12:00:10');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Success)
        ->and($this->order->fresh()->paid_total)->toBe(40000);

    $other = app(StartPayment::class)->handle(tap(app(OpenOrder::class)->handle(DiningTable::factory()->create()), function ($order) {
        app(AddOrderItem::class)->handle($order, Dish::factory()->create(['price' => 10000]));
    }));

    webhook($other, PaymentStatus::Failure, '2026-11-16 12:00:05');
    // Старіший за збережений стан — ігнорується, навіть якщо це «успіх»
    webhook($other, PaymentStatus::Success, '2026-11-16 12:00:02');

    expect($other->fresh()->status)->toBe(PaymentStatus::Failure);
});

it('за однакового часу зміни приймає лише рух уперед', function () {
    $payment = app(StartPayment::class)->handle($this->order);

    webhook($payment, PaymentStatus::Processing, '2026-11-16 12:00:05');
    webhook($payment, PaymentStatus::Success, '2026-11-16 12:00:05');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Success);
});

it('відмова й прострочення не зменшують суму до сплати', function (PaymentStatus $status) {
    $payment = app(StartPayment::class)->handle($this->order, 2000);

    webhook($payment, $status);

    expect($payment->fresh()->status)->toBe($status)
        ->and($this->order->fresh()->paid_total)->toBe(0)
        ->and($this->order->fresh()->amountDue())->toBe(40000)
        ->and($this->waiter->notifications()->count())->toBe(0);

    // Після невдачі гість починає оплату заново — це вже новий платіж
    $retry = app(StartPayment::class)->handle($this->order, 2000);

    expect($retry->id)->not->toBe($payment->id)
        ->and($retry->status)->toBe(PaymentStatus::Created);
})->with([PaymentStatus::Failure, PaymentStatus::Expired]);

it('тримає один активний платіж: та сама сума повертає той самий рахунок, інша — замінює його', function () {
    $start = app(StartPayment::class);
    $first = $start->handle($this->order, 4000);
    $same = $start->handle($this->order, 4000);

    expect($same->id)->toBe($first->id)
        ->and(Payment::count())->toBe(1);

    $other = $start->handle($this->order, 0);

    expect($other->id)->not->toBe($first->id)
        ->and($first->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($this->order->payments()->pendingOnline()->count())->toBe(1);

    // Старий рахунок недійсний і в «провайдера»: оплатити його вже не можна
    expect(app(FakeGateway::class)->getStatus($first->fresh())->status)->toBe(PaymentStatus::Expired);
});

it('не створює другий рахунок, поки перший обробляється', function () {
    $payment = app(StartPayment::class)->handle($this->order, 4000);
    webhook($payment, PaymentStatus::Processing);

    expect(fn () => app(StartPayment::class)->handle($this->order, 0))->toThrow(PaymentException::class)
        ->and(Payment::count())->toBe(1);
});

it('після прострочення рахунку створює новий', function () {
    $first = app(StartPayment::class)->handle($this->order, 4000);

    $this->travel(16)->minutes();

    $second = app(StartPayment::class)->handle($this->order, 4000);

    expect($second->id)->not->toBe($first->id)
        ->and($first->fresh()->status)->toBe(PaymentStatus::Expired);
});

it('не починає оплату, якщо сума змінилася, рахунок сплачено чи замовлення закрите', function () {
    $start = app(StartPayment::class);

    expect(fn () => $start->handle($this->order, 0, 39000))->toThrow(BillChanged::class);

    app(RecordOfflinePayment::class)->handle($this->order, PaymentMethod::Cash, $this->waiter);

    expect(fn () => $start->handle($this->order))->toThrow(BillChanged::class);

    app(CloseOrder::class)->handle($this->order);

    expect(fn () => $start->handle($this->order))->toThrow(PaymentException::class)
        ->and(Payment::where('method', PaymentMethod::Online)->count())->toBe(0);
});

it('платіж покриває зафіксовану суму: дозамовлене лишається до сплати', function () {
    $payment = app(StartPayment::class)->handle($this->order);

    app(AddOrderItem::class)->handle($this->order, Dish::factory()->create(['price' => 5000]));
    webhook($payment, PaymentStatus::Success);

    expect($this->order->fresh()->amountDue())->toBe(5000);

    $rest = app(StartPayment::class)->handle($this->order);

    expect($rest->amount)->toBe(5000);
});

it('рахує чайові від суми платежу й не приймає завеликі чи від\'ємні', function () {
    expect(TipSettings::percents())->toBe([0, 5, 10, 15])
        ->and(TipSettings::defaultPercent())->toBe(10)
        ->and(TipSettings::amountFor(40000, 10))->toBe(4000)
        // Округлення до копійки: 15 % від 33,33 ₴ — це 4,9995 ₴
        ->and(TipSettings::amountFor(3333, 15))->toBe(500)
        ->and(TipSettings::maxFor(40000))->toBe(20000);

    $start = app(StartPayment::class);

    expect(fn () => $start->handle($this->order, 20001))->toThrow(PaymentException::class)
        ->and(fn () => $start->handle($this->order, -1))->toThrow(PaymentException::class)
        ->and($start->handle($this->order, 20000)->tip_amount)->toBe(20000);
});

it('офлайн-оплата гасить неоплачений онлайн-рахунок', function () {
    $payment = app(StartPayment::class)->handle($this->order, 4000);

    app(RecordOfflinePayment::class)->handle($this->order, PaymentMethod::Terminal, $this->waiter);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($this->order->fresh()->amountDue())->toBe(0);
});

it('повертає кошти разом із чайовими й відновлює суму до сплати', function () {
    $payment = app(StartPayment::class)->handle($this->order, 4000);
    webhook($payment, PaymentStatus::Success);

    app(RefundPayment::class)->handle($payment, $this->manager, 'Помилкова оплата');

    expect($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->refunded_amount)->toBe(44000)
        ->and($payment->refunded_at)->not->toBeNull()
        ->and($payment->meta['refund']['reason'])->toBe('Помилкова оплата')
        ->and($payment->meta['refund']['by'])->toBe($this->manager->id)
        ->and($this->order->fresh()->paid_total)->toBe(0)
        ->and($this->order->fresh()->amountDue())->toBe(40000)
        ->and(fn () => app(RefundPayment::class)->handle($payment, $this->manager))->toThrow(PaymentException::class);

    // Після повернення замовлення можна скасувати
    app(CancelOrder::class)->handle($this->order);

    expect($this->order->status)->toBe(OrderStatus::Cancelled);
});

it('офлайн-платіж повертає лише записом у системі', function () {
    $payment = app(RecordOfflinePayment::class)->handle($this->order, PaymentMethod::Cash, $this->waiter);

    app(RefundPayment::class)->handle($payment, $this->manager);

    expect($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->refunded_amount)->toBe(40000)
        ->and($this->order->fresh()->amountDue())->toBe(40000);
});

it('не скасовує замовлення з успішною оплатою, а неоплачений рахунок гасить', function () {
    $payment = app(StartPayment::class)->handle($this->order);

    $other = app(OpenOrder::class)->handle(DiningTable::factory()->create());
    app(AddOrderItem::class)->handle($other, Dish::factory()->create(['price' => 10000]));
    $pending = app(StartPayment::class)->handle($other);
    app(CancelOrder::class)->handle($other);

    expect($pending->fresh()->status)->toBe(PaymentStatus::Expired);

    webhook($payment, PaymentStatus::Success);

    expect(fn () => app(CancelOrder::class)->handle($this->order))->toThrow(OrderException::class);
});

it('чайові не потрапляють у суму замовлення', function () {
    $payment = app(StartPayment::class)->handle($this->order, 6000);
    app(ApplyPaymentStatus::class)->handle($payment, new PaymentStatusData($payment->provider_invoice_id, PaymentStatus::Success));

    expect($this->order->fresh()->subtotal)->toBe(40000)
        ->and($this->order->fresh()->paid_total)->toBe(40000)
        ->and(Payment::sum('tip_amount'))->toEqual(6000);
});
