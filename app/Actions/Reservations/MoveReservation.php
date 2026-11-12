<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Exceptions\Booking\InvalidTransition;
use App\Exceptions\Booking\TableUnavailable;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Services\Booking\AvailabilityService;
use App\Support\LocalTime;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Перенесення на інший столик, час чи кількість гостей — із тими самими перевірками, що й створення.
 */
class MoveReservation
{
    public function __construct(private readonly AvailabilityService $availability) {}

    public function handle(Reservation $reservation, int $tableId, CarbonInterface $startsAt, int $guests): Reservation
    {
        if (! in_array($reservation->status, [ReservationStatus::PendingPayment, ReservationStatus::Confirmed], true)) {
            throw new InvalidTransition('Перенести можна лише бронювання, яке ще не почалося.');
        }

        return DB::transaction(function () use ($reservation, $tableId, $startsAt, $guests): Reservation {
            $table = DiningTable::query()->lockForUpdate()->find($tableId);

            if ($table === null) {
                throw new TableUnavailable(__('booking.errors.table_taken'));
            }

            $this->availability->assertBookable($table, $startsAt, $guests, fromSite: false, ignoreReservationId: $reservation->id);

            $start = LocalTime::of($startsAt);

            $reservation->update([
                'table_id' => $table->id,
                'guests_count' => $guests,
                'starts_at' => $start->utc(),
                'ends_at' => $this->availability->endsAt($start)->utc(),
            ]);

            return $reservation;
        });
    }
}
