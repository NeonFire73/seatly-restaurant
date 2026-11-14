<?php

namespace App\Exceptions\Orders;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;

class InvalidOrderTransition extends OrderException
{
    public static function ofOrder(OrderStatus $from, OrderStatus $to): self
    {
        return new self("Замовлення у статусі «{$from->getLabel()}» не можна перевести в «{$to->getLabel()}».");
    }

    public static function ofItem(OrderItemStatus $from, OrderItemStatus $to): self
    {
        return new self("Позицію у статусі «{$from->getLabel()}» не можна перевести в «{$to->getLabel()}».");
    }
}
