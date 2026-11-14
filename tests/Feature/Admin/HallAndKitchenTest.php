<?php

use App\Actions\Orders\AddOrderItem;
use App\Actions\Orders\ChangeOrderItemStatus;
use App\Actions\Orders\OpenOrder;
use App\Actions\Orders\RecordOfflinePayment;
use App\Actions\Reservations\SeatReservation;
use App\Actions\Tables\CallWaiter;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Enums\WaiterCallStatus;
use App\Exceptions\Orders\OrderException;
use App\Filament\Pages\HallBoard;
use App\Filament\Pages\KitchenBoard;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Hall;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WaiterCall;

use function Pest\Livewire\livewire;

beforeEach(function () {
    openRestaurant();
    $this->hall = Hall::factory()->create();
    $this->table = DiningTable::factory()->create(['hall_id' => $this->hall->id, 'number' => '3']);
    $this->waiter = User::factory()->waiter()->create(['name' => 'Ірина Бондар']);
    $this->manager = User::factory()->manager()->create();
    $this->dish = Dish::factory()->create(['name' => ['uk' => 'Борщ', 'en' => 'Borscht'], 'price' => 18000]);
});

dataset('hall sections', [
    'зал' => ['/admin/hall', [UserRole::Owner, UserRole::Manager, UserRole::Waiter]],
    'кухня' => ['/admin/kitchen', [UserRole::Owner, UserRole::Manager, UserRole::Kitchen]],
    'замовлення' => ['/admin/orders', [UserRole::Owner, UserRole::Manager, UserRole::Waiter]],
]);

it('відкриває зал, кухню й замовлення лише дозволеним ролям', function (string $url, array $allowed) {
    foreach (UserRole::cases() as $role) {
        $response = $this->actingAs(User::factory()->role($role)->create())->get($url);

        if (in_array($role, $allowed, true)) {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
    }
})->with('hall sections');

it('показує стани столиків: вільний, скоро бронювання, до сплати, сплачено, виклик', function () {
    $this->actingAs($this->waiter);

    $page = livewire(HallBoard::class)->assertSee('№3')->assertSee(HallBoard::STATES['free']);

    $reservation = Reservation::factory()->at(kyiv('2026-11-16 12:00'))->create(['table_id' => $this->table->id]);
    $this->travelTo(kyiv('2026-11-16 11:40'));
    $page->call('$refresh')->assertSeeHtml('hb-table hb-soon');

    app(SeatReservation::class)->handle($reservation, $this->waiter);
    $page->call('$refresh')->assertSeeHtml('hb-table hb-busy');

    $order = $this->table->openOrder();
    app(AddOrderItem::class)->handle($order, $this->dish, 2);
    $page->call('$refresh')->assertSeeHtml('hb-table hb-due')->assertSee('360 ₴');

    app(RecordOfflinePayment::class)->handle($order, PaymentMethod::Cash, $this->waiter);
    $page->call('$refresh')->assertSeeHtml('hb-table hb-paid');

    app(CallWaiter::class)->handle($this->table);
    $page->call('$refresh')->assertSeeHtml('hb-table hb-call');
});

it('офіціант веде столик від посадки до закриття замовлення', function () {
    $this->actingAs($this->waiter);

    $page = livewire(HallBoard::class)
        ->call('selectTable', $this->table->id)
        ->assertSee('Посадити без бронювання')
        ->call('openOrder')
        ->assertNotified();

    $order = $this->table->openOrder();

    expect($order)->not->toBeNull()
        ->and($order->waiter_id)->toBe($this->waiter->id);

    $page->assertSee('Борщ')
        ->set('quantity', 2)
        ->set('comment', 'без сметани')
        ->call('addDish', $this->dish->id)
        ->assertSet('quantity', 1)
        ->assertSet('comment', '')
        ->assertSee('без сметани')
        ->assertSee('360 ₴');

    $item = $order->items()->sole();

    expect($item->quantity)->toBe(2)
        ->and($item->added_by)->toBe($this->waiter->id);

    // Сплатити ще не сплачене замовлення не можна закрити
    $page->call('closeOrder')->assertNotified();
    expect($order->fresh()->status)->toBe(OrderStatus::Open);

    $page->call('markServed', $item->id);
    expect($item->fresh()->status)->toBe(OrderItemStatus::Served);

    $page->call('acceptPayment', 'terminal');

    $payment = Payment::sole();

    expect($payment->method)->toBe(PaymentMethod::Terminal)
        ->and($payment->status)->toBe(PaymentStatus::Success)
        ->and($payment->amount)->toBe(36000)
        ->and($payment->recorded_by)->toBe($this->waiter->id)
        ->and($payment->waiter_id)->toBe($this->waiter->id)
        ->and($order->fresh()->amountDue())->toBe(0);

    $page->assertSee('Закрити замовлення')->call('closeOrder');

    expect($order->fresh()->status)->toBe(OrderStatus::Closed)
        ->and($this->table->openOrder())->toBeNull();

    $page->assertSee('Посадити без бронювання');
});

it('офіціант прибирає нову позицію, а розпочату скасувати не може', function () {
    $this->actingAs($this->waiter);
    $order = app(OpenOrder::class)->handle($this->table, $this->waiter);
    $new = app(AddOrderItem::class)->handle($order, $this->dish);
    $cooking = app(AddOrderItem::class)->handle($order, $this->dish);
    app(ChangeOrderItemStatus::class)->handle($cooking, OrderItemStatus::Cooking);

    livewire(HallBoard::class)
        ->call('selectTable', $this->table->id)
        ->assertDontSee('Скасувати замовлення')
        ->call('removeItem', $new->id)
        ->call('cancelItem', $cooking->id)
        ->call('cancelOrder');

    expect($order->items()->count())->toBe(1)
        ->and($cooking->fresh()->status)->toBe(OrderItemStatus::Cooking)
        ->and($order->fresh()->status)->toBe(OrderStatus::Open);
});

it('менеджер скасовує розпочату позицію й неоплачене замовлення', function () {
    $this->actingAs($this->manager);
    $order = app(OpenOrder::class)->handle($this->table, $this->waiter);
    $cooking = app(AddOrderItem::class)->handle($order, $this->dish);
    app(ChangeOrderItemStatus::class)->handle($cooking, OrderItemStatus::Cooking);

    $page = livewire(HallBoard::class)
        ->call('selectTable', $this->table->id)
        ->call('cancelItem', $cooking->id);

    expect($cooking->fresh()->status)->toBe(OrderItemStatus::Cancelled)
        ->and($order->fresh()->subtotal)->toBe(0);

    $page->call('cancelOrder');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('не дає чіпати позицію чужого столика й вимкнений столик', function () {
    $this->actingAs($this->waiter);
    $other = DiningTable::factory()->create(['hall_id' => $this->hall->id]);
    $otherOrder = app(OpenOrder::class)->handle($other);
    $foreign = app(AddOrderItem::class)->handle($otherOrder, $this->dish);
    app(OpenOrder::class)->handle($this->table);

    livewire(HallBoard::class)
        ->call('selectTable', $this->table->id)
        ->call('removeItem', $foreign->id)
        ->call('markServed', $foreign->id);

    expect($foreign->fresh()?->status)->toBe(OrderItemStatus::New);

    $disabled = DiningTable::factory()->create(['hall_id' => $this->hall->id, 'is_active' => false]);

    livewire(HallBoard::class)->call('selectTable', $disabled->id)->call('openOrder');

    expect($disabled->openOrder())->toBeNull();
});

it('садить гостей за бронюванням і відкриває для них замовлення', function () {
    $reservation = Reservation::factory()->at(kyiv('2026-11-16 12:00'))->create([
        'table_id' => $this->table->id,
        'guest_name' => 'Оксана Гриценко',
    ]);
    $this->travelTo(kyiv('2026-11-16 11:55'));
    $this->actingAs($this->waiter);

    livewire(HallBoard::class)
        ->call('selectTable', $this->table->id)
        ->assertSee('Оксана Гриценко')
        ->call('seat', $reservation->id)
        ->assertNotified();

    $order = $this->table->openOrder();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Seated)
        ->and($order->reservation_id)->toBe($reservation->id)
        ->and($order->waiter_id)->toBe($this->waiter->id);
});

it('посадка менеджером лишає замовлення без офіціанта до першої страви', function () {
    $reservation = Reservation::factory()->at(kyiv('2026-11-16 12:00'))->create(['table_id' => $this->table->id]);

    app(SeatReservation::class)->handle($reservation, $this->manager);
    $order = $this->table->openOrder();

    expect($order->waiter_id)->toBeNull();

    app(AddOrderItem::class)->handle($order, $this->dish, 1, null, $this->waiter);

    expect($order->fresh()->waiter_id)->toBe($this->waiter->id);
});

it('посадка не відкриває друге замовлення, якщо попередні гості ще не розрахувалися', function () {
    $existing = app(OpenOrder::class)->handle($this->table, $this->waiter);
    $reservation = Reservation::factory()->at(kyiv('2026-11-16 12:00'))->create(['table_id' => $this->table->id]);

    app(SeatReservation::class)->handle($reservation, $this->waiter);

    expect($reservation->status)->toBe(ReservationStatus::Seated)
        ->and(Order::count())->toBe(1)
        ->and($this->table->openOrder()->id)->toBe($existing->id);
});

it('позначає виклик офіціанта виконаним', function () {
    app(CallWaiter::class)->handle($this->table);
    $this->actingAs($this->waiter);

    livewire(HallBoard::class)
        ->call('selectTable', $this->table->id)
        ->assertSee('Гості кличуть офіціанта')
        ->call('resolveCall')
        ->assertDontSee('Гості кличуть офіціанта');

    $call = WaiterCall::sole();

    expect($call->status)->toBe(WaiterCallStatus::Done)
        ->and($call->handled_by)->toBe($this->waiter->id)
        ->and($call->handled_at)->not->toBeNull();
});

it('приймає офлайн-оплату лише в межах суми до сплати', function () {
    $order = app(OpenOrder::class)->handle($this->table, $this->waiter);
    $record = app(RecordOfflinePayment::class);

    expect(fn () => $record->handle($order, PaymentMethod::Cash, $this->waiter))->toThrow(OrderException::class);

    app(AddOrderItem::class)->handle($order, $this->dish);

    expect(fn () => $record->handle($order, PaymentMethod::Online, $this->waiter))->toThrow(OrderException::class)
        ->and(fn () => $record->handle($order, PaymentMethod::Cash, $this->waiter, 18001))->toThrow(OrderException::class)
        ->and(fn () => $record->handle($order, PaymentMethod::Cash, $this->waiter, 0))->toThrow(OrderException::class);

    $record->handle($order, PaymentMethod::Cash, $this->waiter, 10000);
    $record->handle($order, PaymentMethod::Terminal, $this->waiter);

    expect($order->paid_total)->toBe(18000)
        ->and($order->amountDue())->toBe(0)
        ->and(Payment::count())->toBe(2);

    // Повторний перерахунок нічого не подвоює
    $order->recalculatePaidTotal();

    expect($order->paid_total)->toBe(18000);
});

it('кухня бачить нові позиції й веде їх до готовності', function () {
    $order = app(OpenOrder::class)->handle($this->table, $this->waiter);
    $item = app(AddOrderItem::class)->handle($order, $this->dish, 2, 'гостріше');
    $served = app(AddOrderItem::class)->handle($order, Dish::factory()->create(['name' => ['uk' => 'Узвар']]));
    app(ChangeOrderItemStatus::class)->handle($served, OrderItemStatus::Served);
    $this->actingAs(User::factory()->kitchen()->create());

    $page = livewire(KitchenBoard::class)
        ->assertSee('Борщ')
        ->assertSee('гостріше')
        ->assertSee('Столик №3')
        ->assertDontSee('Узвар')
        ->call('startCooking', $item->id);

    expect($item->fresh()->status)->toBe(OrderItemStatus::Cooking);

    $page->call('markReady', $item->id)->assertDontSee('Борщ');

    expect($item->fresh()->status)->toBe(OrderItemStatus::Ready);

    // Позицію вже подали, поки екран не оновився, — помилка показується сповіщенням
    app(ChangeOrderItemStatus::class)->handle($item->fresh(), OrderItemStatus::Served);
    $page->call('startCooking', $item->id)->assertNotified();
});

it('попереднє замовлення чекає в колонці «До часу», доки не настане час готувати', function () {
    $order = app(OpenOrder::class)->handle($this->table);
    $order->forceFill(['type' => OrderType::Preorder, 'serve_at' => kyiv('2026-11-16 13:00')->utc()])->save();
    app(AddOrderItem::class)->handle($order, Dish::factory()->create(['name' => ['uk' => 'Качка'], 'cook_time_minutes' => 40]));
    $this->actingAs(User::factory()->kitchen()->create());

    $this->travelTo(kyiv('2026-11-16 12:00'));
    livewire(KitchenBoard::class)->assertSeeInOrder(['До часу', 'Качка'])->assertSee('почати о 12:20');

    $this->travelTo(kyiv('2026-11-16 12:25'));
    livewire(KitchenBoard::class)->assertSeeInOrder(['Зараз', 'Качка', 'До часу']);
});

it('у списку замовлень офіціант бачить лише свої', function () {
    $mine = app(OpenOrder::class)->handle($this->table, $this->waiter);
    $foreign = app(OpenOrder::class)->handle(DiningTable::factory()->create(), User::factory()->waiter()->create());

    $this->actingAs($this->waiter);
    livewire(ListOrders::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$foreign]);
    $this->get('/admin/orders/'.$mine->id)->assertOk()->assertSee($mine->number);
    $this->get('/admin/orders/'.$foreign->id)->assertNotFound();

    $this->actingAs($this->manager);
    livewire(ListOrders::class)->assertCanSeeTableRecords([$mine, $foreign]);
    $this->get('/admin/orders/'.$foreign->id)->assertOk();
});
