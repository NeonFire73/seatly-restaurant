<?php

namespace App\Actions\Orders;

use App\Actions\Payments\InvalidatePendingPayments;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Скасовує попереднє замовлення разом із бронюванням (частина 2, п. 4.4). На відміну від CancelOrder
 * не вимагає спершу повернути кошти: чи повертати їх, вирішують правила скасування (п. 3.5),
 * а пізнє скасування лишає оплату закладу.
 */
class CancelPreorder
{
    public function __construct(private InvalidatePendingPayments $invalidatePending) {}

    public function handle(Order $order): Order
    {
        $this->invalidatePending->handle($order);

        return DB::transaction(function () use ($order): Order {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo(OrderStatus::Cancelled)) {
                return $order->refresh();
            }

            $locked->transitionTo(OrderStatus::Cancelled, ['closed_at' => now()]);
            $locked->items()->update(['status' => OrderItemStatus::Cancelled]);

            return $order->refresh();
        });
    }
}
