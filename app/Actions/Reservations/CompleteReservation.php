<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Reservation;

class CompleteReservation
{
    public function handle(Reservation $reservation): Reservation
    {
        $attributes = [];

        // Гості пішли раніше — столик звільняється одразу, а не після розрахункового кінця
        if ($reservation->ends_at->isFuture()) {
            $attributes['ends_at'] = now();
        }

        $reservation->transitionTo(ReservationStatus::Completed, $attributes);

        return $reservation;
    }
}
