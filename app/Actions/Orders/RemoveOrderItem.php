<?php

namespace App\Actions\Orders;

use App\Enums\OrderItemStatus;
use App\Exceptions\Orders\OrderException;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

/**
 * Видаляє позицію, яку кухня ще не взяла в роботу. Пізніші позиції лише скасовуються (CancelOrderItem).
 */
class RemoveOrderItem
{
    public function handle(OrderItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $locked = OrderItem::whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderItemStatus::New) {
                throw new OrderException('Видалити можна лише нову позицію; розпочату скасовує менеджер.');
            }

            $order = $locked->order;
            $locked->delete();
            $order->recalculateSubtotal();
        });
    }
}
