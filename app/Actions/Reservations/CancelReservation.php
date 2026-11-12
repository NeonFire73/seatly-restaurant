<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Mail\ReservationCancelledMail;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\StaffNotifier;
use Illuminate\Support\Facades\Mail;

class CancelReservation
{
    /**
     * @param  User|null  $by  працівник, який скасував; null — скасував сам гість
     */
    public function handle(Reservation $reservation, ?string $reason = null, ?User $by = null): Reservation
    {
        $reservation->transitionTo(ReservationStatus::Cancelled, [
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        if ($reservation->guest_email !== null) {
            Mail::to($reservation->guest_email)
                ->locale($reservation->locale)
                ->queue(new ReservationCancelledMail($reservation));
        }

        StaffNotifier::reservationCancelled($reservation, $by);

        return $reservation;
    }
}
