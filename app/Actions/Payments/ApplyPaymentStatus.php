<?php

namespace App\Actions\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\Payments\PaymentException;
use App\Mail\PreorderPaidMail;
use App\Mail\ReservationConfirmedMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Notifications\StaffNotifier;
use App\Services\Payments\PaymentStatusData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Єдине місце, де змінюється статус платежу (частина 2, п. 6.3). Дані — лише від провайдера:
 * з вебхука або із запиту статусу. Повторний чи запізнілий стан нічого не змінює.
 */
class ApplyPaymentStatus
{
    private const CONFIRMED = 'confirmed';

    private const PREORDER_PAID = 'preorder_paid';

    // Гроші прийшли за бронювання, якого вже немає (прострочене чи скасоване)
    private const ORPHAN = 'orphan';

    /**
     * @return bool чи змінився статус платежу
     */
    public function handle(Payment $payment, PaymentStatusData $data): bool
    {
        $outcome = null;

        $changed = DB::transaction(function () use ($payment, $data, &$outcome): bool {
            // Порядок блокувань той самий, що й у StartPayment і StartReservationPayment:
            // бронювання, замовлення, платіж — інакше взаємне блокування
            $reservation = null;
            $order = null;

            if ($payment->reservation_id !== null) {
                $reservation = Reservation::whereKey($payment->reservation_id)->lockForUpdate()->firstOrFail();
            }

            if ($payment->order_id !== null) {
                $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            }

            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $this->isNews($locked, $data)) {
                return false;
            }

            $previous = $locked->status;
            $at = $data->modifiedAt ?? now()->toImmutable();

            $locked->status = $data->status;
            $locked->provider_modified_at = $at;
            $locked->refunded_amount = $data->refundedAmount;

            if ($data->failureReason !== null) {
                $locked->failure_reason = $data->failureReason;
            }

            if ($data->status === PaymentStatus::Success && $locked->paid_at === null) {
                $locked->paid_at = $at;
            }

            if ($data->status === PaymentStatus::Refunded) {
                $locked->refunded_at ??= $at;
                // Повертається платіж цілком, разом із чайовими (частина 2, розділ 5)
                $locked->refunded_amount = $locked->total();
            }

            if ($previous !== $data->status) {
                $locked->rememberStatus($data->status, $at);
            }

            $locked->save();
            $order?->recalculatePaidTotal();

            if ($reservation !== null && $previous !== $data->status && $data->status === PaymentStatus::Success) {
                $outcome = $this->settleReservation($reservation, $order);
            }

            return $previous !== $data->status;
        });

        $payment->refresh();

        if (! $changed || $payment->status !== PaymentStatus::Success || ! $payment->isOnline()) {
            return $changed;
        }

        if ($payment->reservation_id === null) {
            StaffNotifier::orderPaid($payment);

            return $changed;
        }

        $this->afterReservationPaid($payment, $outcome);

        return $changed;
    }

    /**
     * Успішна оплата підтверджує бронювання й відкриває попереднє замовлення для кухні (частина 2, пп. 3.6 і 4.4).
     */
    private function settleReservation(Reservation $reservation, ?Order $order): string
    {
        if (! in_array($reservation->status, [ReservationStatus::PendingPayment, ReservationStatus::Confirmed], true)) {
            return self::ORPHAN;
        }

        if ($order !== null && $order->status === OrderStatus::AwaitingPayment) {
            $order->transitionTo(OrderStatus::Open, ['opened_at' => now()]);
        }

        if ($reservation->status === ReservationStatus::PendingPayment) {
            $reservation->transitionTo(ReservationStatus::Confirmed, ['expires_at' => null]);

            return self::CONFIRMED;
        }

        return self::PREORDER_PAID;
    }

    private function afterReservationPaid(Payment $payment, ?string $outcome): void
    {
        $reservation = $payment->reservation;

        if ($reservation === null) {
            return;
        }

        if ($outcome === self::ORPHAN) {
            try {
                // Через контейнер, а не конструктор: RefundPayment сам залежить від цієї дії
                app(RefundPayment::class)->handle($payment, null, 'Оплата надійшла після того, як бронювання втратило чинність');
            } catch (PaymentException $e) {
                report($e);
                StaffNotifier::refundFailed($payment);
            }

            return;
        }

        if ($outcome === self::CONFIRMED) {
            if ($reservation->guest_email !== null) {
                Mail::to($reservation->guest_email)
                    ->locale($reservation->locale)
                    ->queue(new ReservationConfirmedMail($reservation, $payment));
            }

            StaffNotifier::reservationCreated($reservation);
        } elseif ($reservation->guest_email !== null) {
            Mail::to($reservation->guest_email)
                ->locale($reservation->locale)
                ->queue(new PreorderPaidMail($reservation, $payment));
        }

        StaffNotifier::reservationPaid($payment);
    }

    /**
     * Вебхуки приходять повторно й не за порядком. Стан застосовується, якщо він не старіший за збережений
     * і не відкочує платіж назад; за однакового часу (Monobank дає його з точністю до секунди) — лише вперед.
     */
    private function isNews(Payment $payment, PaymentStatusData $data): bool
    {
        $currentRank = $payment->status->rank();
        $newRank = $data->status->rank();

        if ($newRank < $currentRank) {
            return false;
        }

        if ($data->status === $payment->status) {
            // Той самий статус — новина лише тоді, коли змінилася сума часткового повернення
            if ($data->status === PaymentStatus::Refunded || $data->refundedAmount === $payment->refunded_amount) {
                return false;
            }
        }

        $stored = $payment->provider_modified_at;

        if ($stored === null || $data->modifiedAt === null) {
            return true;
        }

        if ($data->modifiedAt->lt($stored)) {
            return false;
        }

        if ($data->modifiedAt->eq($stored)) {
            // Сума часткових повернень лише зростає, тож більша сума — теж рух уперед
            return $newRank > $currentRank || $data->refundedAmount > $payment->refunded_amount;
        }

        return true;
    }
}
