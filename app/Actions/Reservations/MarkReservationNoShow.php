<?php

namespace App\Actions\Reservations;

use App\Actions\Orders\CancelPreorder;
use App\Enums\ReservationStatus;
use App\Models\Reservation;

class MarkReservationNoShow
{
    public function __construct(private CancelPreorder $cancelPreorder) {}

    public function handle(Reservation $reservation): Reservation
    {
        $reservation->transitionTo(ReservationStatus::NoShow);

        // Гість не прийшов: страви подавати нікому. Оплата не повертається (частина 2, п. 3.6);
        // менеджер може повернути її вручну з картки платежу
        $preorder = $reservation->preorder()->first();

        if ($preorder !== null) {
            $this->cancelPreorder->handle($preorder);
        }

        return $reservation;
    }
}
