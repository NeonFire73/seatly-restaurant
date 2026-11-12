<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exceptions\Booking\TableUnavailable;
use App\Mail\ReservationConfirmedMail;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Notifications\StaffNotifier;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\TableHold;
use App\Support\LocalTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Створення бронювання — єдиний шлях і для сайту, і для адмінки (частина 2, пп. 1.1, 3.3).
 */
class CreateReservation
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly TableHold $holds,
    ) {}

    public function handle(ReservationData $data): Reservation
    {
        $reservation = DB::transaction(function () use ($data): Reservation {
            // Блокування рядка столика ставить одночасні запити на той самий столик у чергу:
            // другий перевіряє доступність уже після того, як перший створив бронювання
            $table = DiningTable::query()->lockForUpdate()->find($data->tableId);

            if ($table === null) {
                throw new TableUnavailable(__('booking.errors.table_taken'));
            }

            $this->availability->assertBookable(
                $table,
                $data->startsAt,
                $data->guests,
                fromSite: $data->source === ReservationSource::Site,
                holdOwner: $data->holdOwner,
            );

            $start = LocalTime::of($data->startsAt);

            return Reservation::create([
                'table_id' => $table->id,
                'guest_name' => $data->name,
                'guest_phone' => $data->phone,
                'guest_email' => $data->email,
                'guests_count' => $data->guests,
                'starts_at' => $start->utc(),
                'ends_at' => $this->availability->endsAt($start)->utc(),
                // Платне бронювання й попереднє замовлення (статус pending_payment) з'являються на етапі 7
                'status' => ReservationStatus::Confirmed,
                'comment' => $data->comment,
                'locale' => $data->locale,
                'source' => $data->source,
                'fee_amount' => 0,
                'created_by' => $data->createdBy,
            ]);
        });

        if ($data->holdOwner !== null) {
            $this->holds->release($data->holdOwner);
        }

        if ($reservation->guest_email !== null) {
            Mail::to($reservation->guest_email)
                ->locale($reservation->locale)
                ->queue(new ReservationConfirmedMail($reservation));
        }

        StaffNotifier::reservationCreated($reservation);

        return $reservation;
    }
}
