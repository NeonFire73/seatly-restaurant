<?php

namespace App\Console\Commands;

use App\Actions\Reservations\MarkReservationNoShow;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\Booking\BookingSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Фонова задача (частина 2, розділ 8): підтверджене бронювання, за яким гостей так і не посадили.
 */
#[Signature('reservations:mark-no-show')]
#[Description('Позначає «не прийшов» підтверджені бронювання, час яких давно настав')]
class MarkNoShowReservations extends Command
{
    public function handle(MarkReservationNoShow $markNoShow): int
    {
        $reservations = Reservation::query()
            ->where('status', ReservationStatus::Confirmed)
            ->where('starts_at', '<=', now()->subMinutes(BookingSettings::noShowAfter()))
            ->get();

        foreach ($reservations as $reservation) {
            $markNoShow->handle($reservation);
        }

        $this->info("Позначено бронювань: {$reservations->count()}");

        return self::SUCCESS;
    }
}
