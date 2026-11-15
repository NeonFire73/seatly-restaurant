<?php

namespace Database\Seeders;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Payments\FakeGateway;
use Illuminate\Database\Seeder;

/**
 * Демо попередні замовлення (частина 2, п. 10.3): кілька найближчих бронювань із сайту мають оплачене
 * замовлення «до часу», щоб екран кухні й картка бронювання не були порожніми. Записи створюються напряму,
 * без платіжного шлюзу: демо-дані не мають ходити до провайдера.
 */
class PreorderSeeder extends Seeder
{
    private const COUNT = 3;

    public function run(): void
    {
        $dishes = Dish::query()
            ->where('is_available', true)
            ->where('is_preorderable', true)
            ->orderBy('id')
            ->get();

        if ($dishes->count() < 3) {
            return;
        }

        $reservations = Reservation::query()
            ->where('status', ReservationStatus::Confirmed)
            ->where('source', ReservationSource::Site)
            ->where('starts_at', '>', now()->addHours(2))
            ->orderBy('starts_at')
            ->limit(self::COUNT)
            ->get();

        foreach ($reservations as $index => $reservation) {
            // Повторний запуск сидера замовлень не подвоює
            if ($reservation->preorder()->exists()) {
                continue;
            }

            $order = Order::create([
                'table_id' => $reservation->table_id,
                'reservation_id' => $reservation->id,
                'type' => OrderType::Preorder,
                'status' => OrderStatus::Open,
                'serve_at' => $reservation->starts_at,
                'opened_at' => now(),
            ]);

            // Щоразу інший набір страв: зсув по меню залежить від номера бронювання
            foreach ($dishes->slice($index * 4, 3)->values() as $position => $dish) {
                $order->items()->create([
                    'dish_id' => $dish->id,
                    'name' => $dish->getTranslations('name'),
                    'price' => $dish->price,
                    'quantity' => 1 + ($position + $index) % 2,
                    'status' => OrderItemStatus::New,
                ]);
            }

            $order->recalculateSubtotal();

            $payment = new Payment([
                'order_id' => $order->id,
                'reservation_id' => $reservation->id,
                'method' => PaymentMethod::Online,
                'provider' => FakeGateway::NAME,
                'amount' => $order->subtotal,
                'status' => PaymentStatus::Success,
                'paid_at' => now(),
                'meta' => ['locale' => $reservation->locale],
            ]);
            $payment->rememberStatus(PaymentStatus::Created, now());
            $payment->rememberStatus(PaymentStatus::Success, now());
            $payment->save();

            $order->recalculatePaidTotal();
        }
    }
}
