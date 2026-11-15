<?php

namespace App\Services\Booking;

use App\Actions\Payments\RefundPayment;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Reservation;

/**
 * Що повертається гостеві, якщо бронювання скасувати зараз (частина 2, пп. 3.5 і 3.6). Одне місце і для самого
 * скасування, і для тексту, який гість бачить перед ним, — щоб обіцяне й зроблене не розійшлися.
 */
final class RefundPolicy
{
    /**
     * @return list<array{payment: Payment, part: string, fee: int, order: int}>
     */
    public static function refunds(Reservation $reservation, bool $byStaff = false): array
    {
        $minutesLeft = $reservation->minutesUntilStart();
        // Бронювання скасував заклад — плата за нього повертається завжди
        $feeRefundable = $byStaff || $minutesLeft >= BookingSettings::feeRefundUntil();
        $orderRefundable = $minutesLeft >= BookingSettings::preorderFreeCancel();
        $refunds = [];

        foreach ($reservation->payments()->where('status', PaymentStatus::Success)->get() as $payment) {
            $fee = 0;
            $order = 0;

            if ($feeRefundable) {
                $fee = $payment->booking_fee_amount - $payment->refundedFee();
            }

            if ($orderRefundable) {
                $order = $payment->amount - $payment->refundedOrderAmount();
            }

            if ($fee + $order === 0) {
                continue;
            }

            $part = RefundPayment::ALL;

            if ($order === 0) {
                $part = RefundPayment::FEE;
            } elseif ($fee === 0) {
                $part = RefundPayment::ORDER;
            }

            $refunds[] = ['payment' => $payment, 'part' => $part, 'fee' => $fee, 'order' => $order];
        }

        return $refunds;
    }

    /**
     * Скільки гість уже сплатив за бронювання і скільки з цього повернеться після скасування.
     *
     * @return array{paid: int, refund: int}
     */
    public static function summary(Reservation $reservation, bool $byStaff = false): array
    {
        $paid = 0;

        foreach ($reservation->payments()->where('status', PaymentStatus::Success)->get() as $payment) {
            $paid += $payment->refundable();
        }

        $refund = 0;

        foreach (self::refunds($reservation, $byStaff) as $item) {
            $refund += $item['fee'] + $item['order'];
        }

        return ['paid' => $paid, 'refund' => $refund];
    }
}
