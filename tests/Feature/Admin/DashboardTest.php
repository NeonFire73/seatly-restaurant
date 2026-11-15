<?php

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Filament\Widgets\PopularDishes;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\RevenueStats;
use App\Filament\Widgets\ServiceStats;
use App\Filament\Widgets\TodayReservations;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Reports\DashboardReport;

use function Pest\Livewire\livewire;

beforeEach(function () {
    openRestaurant();
    // «Зараз» — понеділок, 16.11.2026, 14:00 за Києвом
    $this->travelTo(kyiv('2026-11-16 14:00'));
    $this->table = DiningTable::factory()->create(['number' => '3']);

    $this->order = fn (array $attributes = []) => Order::create([
        'table_id' => $this->table->id,
        'type' => OrderType::DineIn,
        'status' => OrderStatus::Open,
        'opened_at' => now(),
        ...$attributes,
    ]);

    $this->payment = fn (string $paidAt, array $attributes = []) => Payment::create([
        'order_id' => ($this->order)(['status' => OrderStatus::Closed])->id,
        'method' => PaymentMethod::Cash,
        'amount' => 10000,
        'tip_amount' => 0,
        'booking_fee_amount' => 0,
        'refunded_amount' => 0,
        'currency' => 'UAH',
        'status' => PaymentStatus::Success,
        'paid_at' => kyiv($paidAt)->utc(),
        ...$attributes,
    ]);
});

// ---------- Цифри (частина 2, п. 7.8) ----------

it('рахує виторг за днями закладу без чайових і повернень', function () {
    ($this->payment)('2026-11-16 13:00', ['amount' => 50000, 'tip_amount' => 5000]);
    // Частково повернений платіж за бронювання: плату повернули, страви — ні
    ($this->payment)('2026-11-16 00:30', ['amount' => 30000, 'booking_fee_amount' => 20000, 'refunded_amount' => 20000]);
    // 23:30 попереднього дня за Києвом — у базі це вже той самий день в UTC, але виторг учорашній
    ($this->payment)('2026-11-15 23:30', ['amount' => 7000]);
    ($this->payment)('2026-11-10 12:00', ['amount' => 100000, 'tip_amount' => 1000]);
    ($this->payment)('2026-11-16 12:00', ['status' => PaymentStatus::Refunded, 'refunded_amount' => 10000]);
    ($this->payment)('2026-11-16 12:00', ['status' => PaymentStatus::Failure]);
    ($this->payment)('2026-10-01 12:00', ['amount' => 999900]);

    $byDay = DashboardReport::revenueByDay(30);

    expect(DashboardReport::revenue(1))->toBe(80000)
        ->and(DashboardReport::revenue(7))->toBe(187000)
        ->and(DashboardReport::revenue(30))->toBe(187000)
        ->and($byDay)->toHaveCount(30)
        ->and($byDay['2026-11-15'])->toBe(7000)
        ->and($byDay['2026-11-16'])->toBe(80000)
        ->and(array_key_last($byDay))->toBe('2026-11-16')
        ->and(DashboardReport::tips(30))->toBe(6000);
});

it('рахує зайнятість залу лише за відкритими замовленнями столиків', function () {
    DiningTable::factory()->count(2)->create();
    DiningTable::factory()->create(['is_active' => false]);
    ($this->order)();
    // Оплачене попереднє замовлення столик не займає, закрите — теж
    ($this->order)(['table_id' => DiningTable::factory()->create()->id, 'type' => OrderType::Preorder]);
    ($this->order)(['status' => OrderStatus::Closed]);

    expect(DashboardReport::occupancy())->toBe(['busy' => 1, 'total' => 4, 'percent' => 25]);
});

it('показує бронювання лише на сьогодні, які ще чекають гостей або тривають', function () {
    $evening = Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-16 19:00'))->create(['guests_count' => 4]);
    $seated = Reservation::factory()->at(kyiv('2026-11-16 13:00'))->status(ReservationStatus::Seated)->create(['guests_count' => 2]);
    Reservation::factory()->at(kyiv('2026-11-16 20:00'))->status(ReservationStatus::Cancelled)->create();
    Reservation::factory()->at(kyiv('2026-11-17 19:00'))->create();
    Reservation::factory()->at(kyiv('2026-11-15 23:00'))->create();

    expect(DashboardReport::todayReservations()->modelKeys())->toBe([$seated->id, $evening->id]);
});

it('рахує частку неявок серед бронювань, час яких настав', function () {
    Reservation::factory()->count(3)->at(kyiv('2026-11-14 18:00'))->status(ReservationStatus::Completed)->create();
    Reservation::factory()->at(kyiv('2026-11-15 18:00'))->status(ReservationStatus::NoShow)->create();
    // Скасовані й майбутні до частки не входять; давні — теж
    Reservation::factory()->at(kyiv('2026-11-15 19:00'))->status(ReservationStatus::Cancelled)->create();
    Reservation::factory()->at(kyiv('2026-11-18 18:00'))->create();
    Reservation::factory()->at(kyiv('2026-09-01 18:00'))->status(ReservationStatus::NoShow)->create();

    expect(DashboardReport::noShowRate(30))->toBe(['no_shows' => 1, 'total' => 4, 'percent' => 25.0])
        ->and(DashboardReport::noShowRate(1))->toBe(['no_shows' => 0, 'total' => 0, 'percent' => 0.0]);
});

it('складає перелік найпопулярніших страв без скасованих позицій', function () {
    $borsch = Dish::factory()->create(['name' => ['uk' => 'Борщ', 'en' => 'Borscht']]);
    $varenyky = Dish::factory()->create(['name' => ['uk' => 'Вареники', 'en' => 'Varenyky']]);
    $removed = Dish::factory()->create(['name' => ['uk' => 'Деруни', 'en' => 'Deruny']]);
    $order = ($this->order)();

    $item = fn (Dish $dish, int $quantity, OrderItemStatus $status = OrderItemStatus::Served) => OrderItem::create([
        'order_id' => $order->id,
        'dish_id' => $dish->id,
        'name' => $dish->getTranslations('name'),
        'price' => 10000,
        'quantity' => $quantity,
        'status' => $status,
    ]);

    $item($borsch, 2);
    $item($borsch, 3);
    $item($varenyky, 4);
    $item($varenyky, 10, OrderItemStatus::Cancelled);
    $item($removed, 1);
    $removed->delete();

    expect(DashboardReport::popularDishes(30, 2))->toBe([
        ['name' => 'Борщ', 'quantity' => 5],
        ['name' => 'Вареники', 'quantity' => 4],
    ])->and(DashboardReport::popularDishes(30)[2])->toBe(['name' => 'Деруни', 'quantity' => 1]);
});

// ---------- Хто що бачить (частина 2, п. 7.1) ----------

it('показує кожній ролі свій дашборд', function () {
    Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-16 19:00'))->create(['guest_name' => 'Оксана Гостя']);
    ($this->payment)('2026-11-16 13:00', ['amount' => 123400]);

    $this->actingAs(User::factory()->owner()->create())->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(ServiceStats::class)
        ->assertSeeLivewire(RevenueStats::class)
        ->assertSeeLivewire(RevenueChart::class)
        ->assertSeeLivewire(PopularDishes::class)
        ->assertSeeLivewire(TodayReservations::class);

    $this->actingAs(User::factory()->waiter()->create())->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(ServiceStats::class)
        ->assertSeeLivewire(TodayReservations::class)
        ->assertDontSeeLivewire(RevenueStats::class)
        ->assertDontSeeLivewire(RevenueChart::class)
        ->assertDontSeeLivewire(PopularDishes::class);

    $this->actingAs(User::factory()->role(UserRole::Kitchen)->create())->get('/admin')
        ->assertRedirect('/admin/kitchen');
});

it('віджети показують цифри дня', function () {
    Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-16 19:00'))->create(['guest_name' => 'Оксана Гостя', 'guests_count' => 4]);
    ($this->payment)('2026-11-16 13:00', ['amount' => 123400]);
    $this->actingAs(User::factory()->manager()->create());

    livewire(ServiceStats::class)
        ->assertSee('Найближче о 19:00, столик №3')
        ->assertSee('Зайнято столиків: 0 із 1');

    livewire(RevenueStats::class)->assertSee('1 234 ₴');

    livewire(TodayReservations::class)
        ->assertSee('Оксана Гостя')
        ->assertSee('19:00');
});

it('не віддає офіціантові віджети з виторгом навіть напряму', function () {
    $this->actingAs(User::factory()->waiter()->create());

    expect(RevenueStats::canView())->toBeFalse()
        ->and(RevenueChart::canView())->toBeFalse()
        ->and(PopularDishes::canView())->toBeFalse()
        ->and(ServiceStats::canView())->toBeTrue();
});
