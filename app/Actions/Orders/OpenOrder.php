<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exceptions\Orders\OrderException;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Відкриває замовлення столика (частина 2, пп. 4.1 і 4.3).
 */
class OpenOrder
{
    public function handle(DiningTable $table, ?User $waiter = null, ?Reservation $reservation = null): Order
    {
        return DB::transaction(function () use ($table, $waiter, $reservation): Order {
            // Блокування рядка столика: два офіціанти, що одночасно відкривають той самий столик, стають у чергу
            $locked = DiningTable::whereKey($table->id)->lockForUpdate()->firstOrFail();

            if (! $locked->is_active) {
                throw new OrderException("Столик №{$locked->number} вимкнено, замовлення на нього не відкриваються.");
            }

            if ($locked->openOrder() !== null) {
                throw new OrderException("На столику №{$locked->number} уже є відкрите замовлення.");
            }

            return Order::create([
                'table_id' => $locked->id,
                'reservation_id' => $reservation?->id,
                'waiter_id' => $waiter?->id,
                'type' => OrderType::DineIn,
                'status' => OrderStatus::Open,
                'opened_at' => now(),
            ]);
        });
    }
}
