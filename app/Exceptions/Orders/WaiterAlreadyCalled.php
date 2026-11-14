<?php

namespace App\Exceptions\Orders;

/**
 * Зі столика вже кликали офіціанта менш ніж дві хвилини тому (частина 2, п. 4.5).
 */
class WaiterAlreadyCalled extends OrderException
{
    public static function forTable(string $number): self
    {
        return new self("Зі столика №{$number} офіціанта вже покликали.");
    }
}
