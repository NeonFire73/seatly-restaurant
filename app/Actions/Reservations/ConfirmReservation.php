<?php

namespace App\Actions\Reservations;

use App\Actions\Orders\CancelPreorder;
use App\Actions\Payments\InvalidatePendingPayments;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\Booking\BookingException;
use App\Mail\ReservationConfirmedMail;
use App\Models\Reservation;
use App\Notifications\StaffNotifier;
use Illuminate\Support\Facades\Mail;

/**
 * Менеджер підтверджує неоплачене бронювання без оплати (частина 2, п. 7.4) — наприклад, гість
 * зателефонував, бо оплата не проходить. Плата за бронювання знімається, неоплачене попереднє
 * замовлення скасовується: без оплати кухня його не готує.
 */
class ConfirmReservation
{
    public function __construct(
        private InvalidatePendingPayments $invalidatePending,
        private CancelPreorder $cancelPreorder,
    ) {}

    public function handle(Reservation $reservation): Reservation
    {
        if (! $this->invalidatePending->handle($reservation)) {
            throw new BookingException('Гість саме оплачує це бронювання: дочекайтеся результату.');
        }

        $reservation->refresh();

        // Оплата встигла пройти, поки гасили рахунок: бронювання вже підтверджене звичайним шляхом
        if ($reservation->status === ReservationStatus::Confirmed) {
            return $reservation;
        }

        $preorder = $reservation->preorder()->first();

        if ($preorder !== null && $preorder->status === OrderStatus::AwaitingPayment) {
            $this->cancelPreorder->handle($preorder);
        }

        $reservation->transitionTo(ReservationStatus::Confirmed, ['expires_at' => null, 'fee_amount' => 0]);

        if ($reservation->guest_email !== null) {
            Mail::to($reservation->guest_email)
                ->locale($reservation->locale)
                ->queue(new ReservationConfirmedMail($reservation));
        }

        StaffNotifier::reservationCreated($reservation);

        return $reservation;
    }
}
