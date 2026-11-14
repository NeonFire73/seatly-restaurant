<?php

namespace App\Actions\Orders;

use App\Enums\OrderItemStatus;
use App\Exceptions\Orders\OrderException;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Скасовує позицію, яку вже почали готувати чи подали: вона лишається в замовленні для історії,
 * але не входить у суму. Право на це мають лише власник і менеджер (частина 2, п. 4.2).
 */
class CancelOrderItem
{
    public function handle(OrderItem $item, User $by): OrderItem
    {
        if (! $by->isManagement()) {
            throw new OrderException('Скасувати розпочату позицію може лише менеджер або власник.');
        }

        return DB::transaction(function () use ($item): OrderItem {
            $locked = OrderItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $locked->transitionTo(OrderItemStatus::Cancelled);
            $locked->order->recalculateSubtotal();

            return $item->refresh();
        });
    }
}
