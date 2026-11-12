<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Exceptions\Booking\TableUnavailable;
use App\Models\Reservation;
use App\Services\Booking\AvailabilityService;
use Illuminate\Support\Facades\DB;

/**
 * Менеджер повертає помилково позначене «не прийшов» бронювання в «підтверджено».
 */
class RestoreReservation
{
    public function __construct(private readonly AvailabilityService $availability) {}

    public function handle(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation): Reservation {
            $table = $reservation->table()->lockForUpdate()->firstOrFail();

            // Поки бронювання вважалося неявкою, столик могли віддати іншим гостям
            if (! $this->availability->isFree($table, $reservation->starts_at, $reservation->ends_at, $reservation->id)) {
                throw new TableUnavailable(__('booking.errors.table_taken'));
            }

            $reservation->transitionTo(ReservationStatus::Confirmed);

            return $reservation;
        });
    }
}
