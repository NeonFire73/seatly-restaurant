<?php

namespace App\Actions\Reservations;

use App\Actions\Orders\CancelPreorder;
use App\Actions\Payments\InvalidatePendingPayments;
use App\Enums\ReservationStatus;
use App\Models\Reservation;

/**
 * Неоплачене вчасно бронювання звільняє столик (частина 2, пп. 3.4, 3.6 і розділ 8).
 */
class ExpireReservation
{
    public function __construct(
        private InvalidatePendingPayments $invalidatePending,
        private CancelPreorder $cancelPreorder,
    ) {}

    /**
     * @return bool false — бронювання лишилося як є: гість саме платить або вже сплатив
     */
    public function handle(Reservation $reservation): bool
    {
        // Рахунок гаситься першим. Якщо провайдер відмовив, гість саме вводить картку: результат
        // покаже вебхук або payments:sync, а бронювання дочекається наступного запуску
        if (! $this->invalidatePending->handle($reservation)) {
            return false;
        }

        $reservation->refresh();

        if ($reservation->status !== ReservationStatus::PendingPayment) {
            return false;
        }

        $reservation->transitionTo(ReservationStatus::Expired);

        $preorder = $reservation->preorder()->first();

        if ($preorder !== null) {
            $this->cancelPreorder->handle($preorder);
        }

        return true;
    }
}
