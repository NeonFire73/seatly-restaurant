<?php

namespace App\Actions\Orders;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Exceptions\Orders\OrderException;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Скасовує замовлення разом з усіма позиціями. Якщо за ним уже є оплата, спершу потрібне повернення коштів (етап 6).
 */
class CancelOrder
{
    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->paid_total > 0) {
                throw new OrderException('За замовленням уже є оплата: спершу поверніть кошти.');
            }

            $locked->transitionTo(OrderStatus::Cancelled, ['closed_at' => now()]);
            $locked->items()->update(['status' => OrderItemStatus::Cancelled]);
            $locked->recalculateSubtotal();

            return $order->refresh();
        });
    }
}
