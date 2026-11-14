<?php

namespace App\Actions\Orders;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Exceptions\Orders\OrderException;
use App\Models\OrderItem;

/**
 * Рух позиції кухнею та офіціантом: нова → готується → готово → подано.
 * Скасування — окрема дія CancelOrderItem, бо воно змінює суму й потребує прав.
 */
class ChangeOrderItemStatus
{
    public function handle(OrderItem $item, OrderItemStatus $status): OrderItem
    {
        if ($status === OrderItemStatus::Cancelled) {
            throw new OrderException('Позицію скасовує менеджер окремою дією.');
        }

        if ($item->order->status !== OrderStatus::Open) {
            throw new OrderException('Замовлення вже не відкрите, статуси позицій не змінюються.');
        }

        $item->transitionTo($status);

        return $item;
    }
}
