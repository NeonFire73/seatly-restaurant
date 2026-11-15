<?php

namespace App\Actions\Orders;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\ReservationStatus;
use App\Exceptions\Orders\OrderException;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Reservation;
use App\Services\Booking\BookingSettings;
use Illuminate\Support\Facades\DB;

/**
 * Попереднє замовлення до бронювання (частина 2, п. 4.4). Створюється неоплаченим: кухня побачить його
 * лише після оплати. Тексти помилок показуються гостеві, тому беруться з мовних файлів.
 */
class CreatePreorder
{
    public const MAX_LINES = 30;

    public function __construct(private CancelPreorder $cancelPreorder) {}

    /**
     * @param  array<int, int>  $items  ідентифікатор страви => кількість
     */
    public function handle(Reservation $reservation, array $items): Order
    {
        $items = array_filter($items, fn (int $quantity) => $quantity > 0);

        if ($items === [] || count($items) > self::MAX_LINES) {
            throw new OrderException(__('booking.preorder.errors.empty'));
        }

        foreach ($items as $quantity) {
            if ($quantity > AddOrderItem::MAX_QUANTITY) {
                throw new OrderException(__('booking.preorder.errors.quantity', ['max' => AddOrderItem::MAX_QUANTITY]));
            }
        }

        $dishes = Dish::query()->whereKey(array_keys($items))->get()->keyBy('id');

        foreach (array_keys($items) as $dishId) {
            $dish = $dishes->get($dishId);

            if ($dish === null || ! $dish->is_available || ! $dish->is_preorderable) {
                throw new OrderException(__('booking.preorder.errors.dish_unavailable'));
            }
        }

        return DB::transaction(function () use ($reservation, $items, $dishes): Order {
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [ReservationStatus::PendingPayment, ReservationStatus::Confirmed], true)) {
                throw new OrderException(__('booking.preorder.errors.closed'));
            }

            if ($locked->minutesUntilStart() < BookingSettings::preorderMinLead()) {
                throw new OrderException(__('booking.preorder.errors.too_late'));
            }

            $existing = $locked->preorder()->first();

            if ($existing !== null) {
                if ($existing->status !== OrderStatus::AwaitingPayment) {
                    throw new OrderException(__('booking.preorder.errors.exists'));
                }

                // Гість передумав і зібрав кошик заново: неоплачений варіант замінюється новим
                $this->cancelPreorder->handle($existing);
            }

            $order = Order::create([
                'table_id' => $locked->table_id,
                'reservation_id' => $locked->id,
                'type' => OrderType::Preorder,
                'status' => OrderStatus::AwaitingPayment,
                'serve_at' => $locked->starts_at,
            ]);

            foreach ($items as $dishId => $quantity) {
                /** @var Dish $dish */
                $dish = $dishes->get($dishId);

                $order->items()->create([
                    'dish_id' => $dish->id,
                    'name' => $dish->getTranslations('name'),
                    'price' => $dish->price,
                    'quantity' => $quantity,
                    'status' => OrderItemStatus::New,
                ]);
            }

            $order->recalculateSubtotal();

            return $order;
        });
    }
}
