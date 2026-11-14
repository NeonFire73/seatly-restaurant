<?php

namespace App\Actions\Orders;

use App\Actions\Payments\InvalidatePendingPayments;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Exceptions\Orders\OrderException;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Скасовує замовлення разом з усіма позиціями. Якщо за ним уже є оплата, спершу потрібне повернення коштів
 * (розділ «Платежі» в адмінці).
 */
class CancelOrder
{
    public function __construct(private InvalidatePendingPayments $invalidatePending) {}

    public function handle(Order $order): Order
    {
        // Спершу гаситься неоплачений онлайн-рахунок: гість не має сплатити замовлення, якого вже немає
        if (! $this->invalidatePending->handle($order)) {
            throw new OrderException('Гість саме оплачує це замовлення онлайн: дочекайтеся результату.');
        }

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
