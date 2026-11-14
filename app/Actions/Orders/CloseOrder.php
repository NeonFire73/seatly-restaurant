<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\Orders\OrderException;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Закриває повністю сплачене замовлення; столик після цього вільний (частина 2, п. 4.1).
 */
class CloseOrder
{
    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            // Суму рахуємо заново, а не беремо збережену: позицію могли додати за мить до закриття
            $locked->recalculateSubtotal();

            if ($locked->amountDue() > 0) {
                throw new OrderException('Замовлення не сплачене: до сплати '.Money::format($locked->amountDue(), 'uk').'.');
            }

            $locked->transitionTo(OrderStatus::Closed, ['closed_at' => now()]);

            return $order->refresh();
        });
    }
}
