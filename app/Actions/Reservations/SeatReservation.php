<?php

namespace App\Actions\Reservations;

use App\Actions\Orders\OpenOrder;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Гості прийшли й сіли за столик. Разом із посадкою відкривається замовлення столика,
 * щоб офіціант одразу міг додавати страви (частина 2, п. 4.3).
 */
class SeatReservation
{
    public function __construct(private readonly OpenOrder $openOrder) {}

    public function handle(Reservation $reservation, ?User $by = null): Reservation
    {
        DB::transaction(function () use ($reservation, $by): void {
            $reservation->transitionTo(ReservationStatus::Seated, ['seated_at' => now()]);

            // Попередні гості могли ще не розрахуватися — тоді їхнє замовлення лишається, нове не відкривається
            if ($reservation->table->openOrder() !== null) {
                return;
            }

            $waiter = null;

            // Менеджер, який посадив гостей, столик не обслуговує: офіціантом стане той, хто першим додасть страву
            if ($by !== null && $by->hasRole(UserRole::Waiter)) {
                $waiter = $by;
            }

            $this->openOrder->handle($reservation->table, $waiter, $reservation);
        });

        return $reservation;
    }
}
