<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Reservation;

class MarkReservationNoShow
{
    public function handle(Reservation $reservation): Reservation
    {
        $reservation->transitionTo(ReservationStatus::NoShow);

        return $reservation;
    }
}
