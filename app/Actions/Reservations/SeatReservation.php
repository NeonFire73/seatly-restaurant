<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Reservation;

/**
 * Гості прийшли й сіли за столик. Замовлення столика додається на етапі 5.
 */
class SeatReservation
{
    public function handle(Reservation $reservation): Reservation
    {
        $reservation->transitionTo(ReservationStatus::Seated, ['seated_at' => now()]);

        return $reservation;
    }
}
