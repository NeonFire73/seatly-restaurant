<?php

namespace App\Actions\Reservations;

use App\Actions\Orders\CancelPreorder;
use App\Actions\Payments\InvalidatePendingPayments;
use App\Actions\Payments\RefundPayment;
use App\Enums\ReservationStatus;
use App\Exceptions\Booking\BookingException;
use App\Exceptions\Payments\PaymentException;
use App\Mail\ReservationCancelledMail;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\StaffNotifier;
use App\Services\Booking\RefundPolicy;
use Illuminate\Support\Facades\Mail;

/**
 * Скасування бронювання гостем або персоналом (частина 2, пп. 3.5 і 3.6): столик звільняється,
 * попереднє замовлення скасовується, сплачене повертається за правилами з RefundPolicy.
 */
class CancelReservation
{
    public function __construct(
        private InvalidatePendingPayments $invalidatePending,
        private CancelPreorder $cancelPreorder,
        private RefundPayment $refundPayment,
    ) {}

    /**
     * @param  User|null  $by  працівник, який скасував; null — скасував сам гість
     */
    public function handle(Reservation $reservation, ?string $reason = null, ?User $by = null): Reservation
    {
        // Спершу гаситься неоплачений рахунок: гість не має сплатити бронювання, якого вже немає
        if (! $this->invalidatePending->handle($reservation)) {
            throw new BookingException(__('booking.page.payment_in_progress'));
        }

        // Поки рахунок гасили, він міг виявитися сплаченим — тоді бронювання вже підтверджене
        $reservation->refresh();

        // Рахується до зміни статусу й до скасування замовлення: потім платежі вже виглядатимуть інакше
        $refunds = RefundPolicy::refunds($reservation, $by !== null);

        $reservation->transitionTo(ReservationStatus::Cancelled, [
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        $preorder = $reservation->preorder()->first();

        if ($preorder !== null) {
            $this->cancelPreorder->handle($preorder);
        }

        $refunded = 0;

        foreach ($refunds as $refund) {
            try {
                $this->refundPayment->handle($refund['payment'], $by, 'Скасування бронювання '.$reservation->code, $refund['part']);
                $refunded += $refund['fee'] + $refund['order'];
            } catch (PaymentException $e) {
                // Бронювання вже скасоване; гроші менеджер поверне вручну з картки платежу
                report($e);
                StaffNotifier::refundFailed($refund['payment']);
            }
        }

        if ($reservation->guest_email !== null) {
            Mail::to($reservation->guest_email)
                ->locale($reservation->locale)
                ->queue(new ReservationCancelledMail($reservation, $refunded));
        }

        StaffNotifier::reservationCancelled($reservation, $by);

        return $reservation;
    }
}
