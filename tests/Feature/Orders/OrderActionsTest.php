<?php

use App\Actions\Orders\AddOrderItem;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CancelOrderItem;
use App\Actions\Orders\ChangeOrderItemStatus;
use App\Actions\Orders\CloseOrder;
use App\Actions\Orders\OpenOrder;
use App\Actions\Orders\RemoveOrderItem;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exceptions\Orders\InvalidOrderTransition;
use App\Exceptions\Orders\OrderException;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\User;

beforeEach(function () {
    $this->table = DiningTable::factory()->create(['number' => '7']);
    $this->waiter = User::factory()->waiter()->create();
    $this->order = app(OpenOrder::class)->handle($this->table, $this->waiter);
});

it('відкриває замовлення столика з номером і офіціантом', function () {
    expect($this->order->status)->toBe(OrderStatus::Open)
        ->and($this->order->type)->toBe(OrderType::DineIn)
        ->and($this->order->waiter_id)->toBe($this->waiter->id)
        ->and($this->order->number)->toBe(sprintf('%06d', $this->order->id))
        ->and($this->order->opened_at)->not->toBeNull()
        ->and($this->table->openOrder()?->id)->toBe($this->order->id);
});

it('не відкриває друге замовлення на тому самому столику', function () {
    expect(fn () => app(OpenOrder::class)->handle($this->table))->toThrow(OrderException::class);

    // На іншому столику — можна
    expect(app(OpenOrder::class)->handle(DiningTable::factory()->create())->status)->toBe(OrderStatus::Open);
});

it('не відкриває замовлення на вимкненому столику', function () {
    $table = DiningTable::factory()->create(['is_active' => false]);

    expect(fn () => app(OpenOrder::class)->handle($table))->toThrow(OrderException::class);
});

it('копіює назву й ціну страви в позицію та перераховує суму', function () {
    $dish = Dish::factory()->create(['name' => ['uk' => 'Борщ', 'en' => 'Borscht'], 'price' => 18500]);

    $item = app(AddOrderItem::class)->handle($this->order, $dish, 2, '  без сметани  ', $this->waiter);

    expect($item->status)->toBe(OrderItemStatus::New)
        ->and($item->getTranslations('name'))->toBe(['uk' => 'Борщ', 'en' => 'Borscht'])
        ->and($item->price)->toBe(18500)
        ->and($item->comment)->toBe('без сметани')
        ->and($item->added_by)->toBe($this->waiter->id)
        ->and($this->order->subtotal)->toBe(37000);

    // Зміна меню не змінює вже складений рахунок
    $dish->update(['name' => ['uk' => 'Борщ зелений'], 'price' => 99900]);

    expect($item->fresh()->price)->toBe(18500)
        ->and($item->fresh()->getTranslation('name', 'uk'))->toBe('Борщ');

    app(AddOrderItem::class)->handle($this->order, Dish::factory()->create(['price' => 5000]));

    expect($this->order->subtotal)->toBe(42000)
        ->and($this->order->amountDue())->toBe(42000);
});

it('не додає недоступну страву й некоректну кількість', function () {
    $add = app(AddOrderItem::class);

    expect(fn () => $add->handle($this->order, Dish::factory()->unavailable()->create()))->toThrow(OrderException::class)
        ->and(fn () => $add->handle($this->order, Dish::factory()->create(), 0))->toThrow(OrderException::class)
        ->and(fn () => $add->handle($this->order, Dish::factory()->create(), 100))->toThrow(OrderException::class);

    $deleted = Dish::factory()->create();
    $deleted->delete();

    expect(fn () => $add->handle($this->order, $deleted))->toThrow(OrderException::class)
        ->and($this->order->items()->count())->toBe(0);
});

it('веде позицію лише вперед і дозволяє пропустити крок', function () {
    $change = app(ChangeOrderItemStatus::class);
    $item = app(AddOrderItem::class)->handle($this->order, Dish::factory()->create());

    $change->handle($item, OrderItemStatus::Cooking);
    $change->handle($item, OrderItemStatus::Ready);

    expect(fn () => $change->handle($item, OrderItemStatus::Cooking))->toThrow(InvalidOrderTransition::class)
        ->and(fn () => $change->handle($item, OrderItemStatus::Ready))->toThrow(InvalidOrderTransition::class);

    $change->handle($item, OrderItemStatus::Served);

    expect($item->fresh()->status)->toBe(OrderItemStatus::Served)
        ->and(fn () => $change->handle($item, OrderItemStatus::New))->toThrow(InvalidOrderTransition::class);

    // Напій подають одразу, без кухні
    $drink = app(AddOrderItem::class)->handle($this->order, Dish::factory()->create());
    $change->handle($drink, OrderItemStatus::Served);

    expect($drink->fresh()->status)->toBe(OrderItemStatus::Served)
        // Скасування — окрема дія з перевіркою прав
        ->and(fn () => $change->handle($drink, OrderItemStatus::Cancelled))->toThrow(OrderException::class);
});

it('нову позицію видаляє, а розпочату скасовує лише менеджер', function () {
    $add = app(AddOrderItem::class);
    $new = $add->handle($this->order, Dish::factory()->create(['price' => 10000]));
    $cooking = $add->handle($this->order, Dish::factory()->create(['price' => 20000]));
    app(ChangeOrderItemStatus::class)->handle($cooking, OrderItemStatus::Cooking);

    expect(fn () => app(RemoveOrderItem::class)->handle($cooking))->toThrow(OrderException::class);

    app(RemoveOrderItem::class)->handle($new);

    expect($this->order->fresh()->subtotal)->toBe(20000)
        ->and($this->order->items()->count())->toBe(1)
        ->and(fn () => app(CancelOrderItem::class)->handle($cooking, $this->waiter))->toThrow(OrderException::class);

    app(CancelOrderItem::class)->handle($cooking, User::factory()->manager()->create());

    // Скасована позиція лишається в історії, але в суму не входить
    expect($cooking->fresh()->status)->toBe(OrderItemStatus::Cancelled)
        ->and($this->order->fresh()->subtotal)->toBe(0)
        ->and($this->order->items()->count())->toBe(1);
});

it('закриває замовлення лише коли до сплати нуль', function () {
    app(AddOrderItem::class)->handle($this->order, Dish::factory()->create(['price' => 30000]));

    expect(fn () => app(CloseOrder::class)->handle($this->order))->toThrow(OrderException::class)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Open);

    $this->order->forceFill(['paid_total' => 30000])->save();
    app(CloseOrder::class)->handle($this->order);

    expect($this->order->status)->toBe(OrderStatus::Closed)
        ->and($this->order->closed_at)->not->toBeNull()
        // Столик вільний: можна відкривати наступне замовлення
        ->and($this->table->openOrder())->toBeNull()
        ->and(app(OpenOrder::class)->handle($this->table)->status)->toBe(OrderStatus::Open);
});

it('не змінює закрите замовлення', function () {
    $item = app(AddOrderItem::class)->handle($this->order, Dish::factory()->create(['price' => 10000]));
    $this->order->forceFill(['paid_total' => 10000])->save();
    app(CloseOrder::class)->handle($this->order);

    expect(fn () => app(AddOrderItem::class)->handle($this->order, Dish::factory()->create()))->toThrow(OrderException::class)
        ->and(fn () => app(ChangeOrderItemStatus::class)->handle($item->fresh(), OrderItemStatus::Served))->toThrow(OrderException::class)
        ->and(fn () => app(CloseOrder::class)->handle($this->order))->toThrow(InvalidOrderTransition::class)
        ->and(fn () => app(CancelOrder::class)->handle($this->order))->toThrow(OrderException::class);
});

it('скасовує неоплачене замовлення разом із позиціями', function () {
    $item = app(AddOrderItem::class)->handle($this->order, Dish::factory()->create(['price' => 10000]));

    app(CancelOrder::class)->handle($this->order);

    expect($this->order->status)->toBe(OrderStatus::Cancelled)
        ->and($this->order->subtotal)->toBe(0)
        ->and($item->fresh()->status)->toBe(OrderItemStatus::Cancelled)
        ->and($this->table->openOrder())->toBeNull();
});

it('не скасовує замовлення, за яким уже є оплата', function () {
    app(AddOrderItem::class)->handle($this->order, Dish::factory()->create(['price' => 10000]));
    $this->order->forceFill(['paid_total' => 5000])->save();

    expect(fn () => app(CancelOrder::class)->handle($this->order))->toThrow(OrderException::class)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Open);
});
