<?php

namespace App\Exceptions\Booking;

use App\Enums\ReservationStatus;

class InvalidTransition extends BookingException
{
    public static function between(ReservationStatus $from, ReservationStatus $to): self
    {
        return new self("Бронювання у статусі «{$from->getLabel()}» не можна перевести в «{$to->getLabel()}».");
    }
}
