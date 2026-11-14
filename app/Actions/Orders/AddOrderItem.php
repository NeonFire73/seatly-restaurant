<?php

namespace App\Actions\Orders;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Exceptions\Orders\OrderException;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Додає страву до відкритого замовлення. Назва й ціна копіюються в позицію:
 * подальша зміна меню не змінює рахунок (частина 2, п. 2.3).
 */
class AddOrderItem
{
    public const MAX_QUANTITY = 99;

    public function handle(Order $order, Dish $dish, int $quantity = 1, ?string $comment = null, ?User $by = null): OrderItem
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new OrderException('Кількість має бути від 1 до '.self::MAX_QUANTITY.'.');
        }

        if ($dish->trashed() || ! $dish->is_available) {
            throw new OrderException("Страви «{$dish->name}» зараз немає в наявності.");
        }

        $comment = mb_substr(trim((string) $comment), 0, 255);

        if ($comment === '') {
            $comment = null;
        }

        return DB::transaction(function () use ($order, $dish, $quantity, $comment, $by): OrderItem {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Open) {
                throw new OrderException('Додавати позиції можна лише до відкритого замовлення.');
            }

            $item = $locked->items()->create([
                'dish_id' => $dish->id,
                'name' => $dish->getTranslations('name'),
                'price' => $dish->price,
                'quantity' => $quantity,
                'status' => OrderItemStatus::New,
                'comment' => $comment,
                'added_by' => $by?->id,
            ]);

            // Замовлення без офіціанта (гостей посадив менеджер) дістається тому офіціантові, хто першим додав страву
            if ($locked->waiter_id === null && $by !== null && $by->hasRole(UserRole::Waiter)) {
                $locked->waiter_id = $by->id;
            }

            $locked->recalculateSubtotal();
            $order->refresh();

            return $item;
        });
    }
}
