<?php

namespace Database\Seeders;

use App\Actions\Orders\AddOrderItem;
use App\Actions\Orders\ChangeOrderItemStatus;
use App\Actions\Orders\OpenOrder;
use App\Enums\OrderItemStatus;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Демо-замовлення: два відкриті столики з позиціями в різних статусах, щоб сторінку столика
 * було на чому показати. Історію закритих замовлень і платежів створює OrderHistorySeeder.
 */
class OrderSeeder extends Seeder
{
    // номер столика => [[порядковий номер страви в меню, кількість, статус], …]
    private const PLAN = [
        '2' => [
            [0, 2, OrderItemStatus::Served],
            [7, 1, OrderItemStatus::Cooking],
            [14, 1, OrderItemStatus::New],
            [30, 2, OrderItemStatus::Served],
        ],
        '5' => [
            [3, 1, OrderItemStatus::Ready],
            [9, 3, OrderItemStatus::New],
        ],
    ];

    public function run(OpenOrder $openOrder, AddOrderItem $addItem, ChangeOrderItemStatus $changeStatus): void
    {
        $dishes = Dish::where('is_available', true)->orderBy('id')->get()->values();
        $waiters = User::where('email', 'like', 'waiter%')->orderBy('id')->get();

        if ($dishes->isEmpty()) {
            return;
        }

        $index = 0;

        foreach (self::PLAN as $number => $items) {
            $table = DiningTable::where('number', (string) $number)->first();

            // Повторний запуск сидера не має падати на правилі «одне відкрите замовлення на столик»
            if ($table === null || $table->openOrder() !== null) {
                continue;
            }

            $waiter = null;

            if ($waiters->isNotEmpty()) {
                $waiter = $waiters[$index % $waiters->count()];
            }

            $order = $openOrder->handle($table, $waiter);

            foreach ($items as [$dishIndex, $quantity, $status]) {
                $item = $addItem->handle($order, $dishes[$dishIndex % $dishes->count()], $quantity, null, $waiter);

                if ($status !== OrderItemStatus::New) {
                    $changeStatus->handle($item, $status);
                }
            }

            $index++;
        }
    }
}
