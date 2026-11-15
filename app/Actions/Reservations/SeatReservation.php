<?php

namespace App\Actions\Reservations;

use App\Actions\Orders\CancelPreorder;
use App\Actions\Orders\OpenOrder;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Exceptions\Orders\OrderException;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Гості прийшли й сіли за столик. Разом із посадкою відкривається замовлення столика,
 * щоб офіціант одразу міг додавати страви (частина 2, п. 4.3). Оплачене попереднє замовлення
 * саме стає замовленням столика (п. 4.4).
 */
class SeatReservation
{
    public function __construct(
        private readonly OpenOrder $openOrder,
        private readonly CancelPreorder $cancelPreorder,
    ) {}

    public function handle(Reservation $reservation, ?User $by = null): Reservation
    {
        $preorder = $reservation->preorder()->first();

        // Гість почав оформлювати попереднє замовлення, але так і не оплатив: за столиком воно вже не потрібне
        if ($preorder !== null && $preorder->status === OrderStatus::AwaitingPayment) {
            $this->cancelPreorder->handle($preorder);
            $preorder = null;
        }

        $occupied = $reservation->table->openOrder() !== null;

        // Два відкриті замовлення на столику заборонені (п. 4.1), а попереднє загубити не можна
        if ($preorder !== null && $preorder->type === OrderType::Preorder && $occupied) {
            throw new OrderException("На столику №{$reservation->table->number} ще відкрите замовлення попередніх гостей: закрийте його, щоб посадити гостей із попереднім замовленням.");
        }

        DB::transaction(function () use ($reservation, $by, $preorder, $occupied): void {
            $reservation->transitionTo(ReservationStatus::Seated, ['seated_at' => now()]);

            $waiter = null;

            // Менеджер, який посадив гостей, столик не обслуговує: офіціантом стане той, хто першим додасть страву
            if ($by !== null && $by->hasRole(UserRole::Waiter)) {
                $waiter = $by;
            }

            if ($preorder !== null && $preorder->type === OrderType::Preorder) {
                // Тип змінюється, а час подачі лишається: за ним видно, що замовлення почалося як попереднє
                $preorder->forceFill(['type' => OrderType::DineIn, 'waiter_id' => $waiter?->id])->save();

                return;
            }

            // Попередні гості могли ще не розрахуватися — тоді їхнє замовлення лишається, нове не відкривається
            if ($occupied) {
                return;
            }

            $this->openOrder->handle($reservation->table, $waiter, $reservation);
        });

        return $reservation;
    }
}
