<?php

namespace App\Actions\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\Payments\BillChanged;
use App\Exceptions\Payments\GatewayException;
use App\Exceptions\Payments\PaymentException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Payments\GatewayManager;
use App\Support\Localization;
use Illuminate\Support\Facades\DB;

/**
 * Починає оплату бронювання (частина 2, пп. 3.6 і 4.4): плата за бронювання, попереднє замовлення
 * або обидва одним платежем і одним рахунком у провайдера. Чайові тут не пропонуються.
 */
class StartReservationPayment
{
    public function __construct(
        private GatewayManager $gateways,
        private InvalidatePendingPayments $invalidatePending,
    ) {}

    public function handle(Reservation $reservation, ?string $locale = null): Payment
    {
        if (! Localization::isSupported($locale)) {
            $locale = $reservation->locale;
        }

        return DB::transaction(function () use ($reservation, $locale): Payment {
            // Порядок блокувань: бронювання, замовлення, платіж — той самий, що в ApplyPaymentStatus
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $pending = $locked->status === ReservationStatus::PendingPayment;

            if (! $pending && $locked->status !== ReservationStatus::Confirmed) {
                throw new PaymentException(__('payment.errors.reservation_closed'));
            }

            if ($pending && $locked->expires_at !== null && $locked->expires_at->isPast()) {
                throw new PaymentException(__('payment.errors.reservation_expired'));
            }

            $order = $locked->preorder()->first();

            if ($order !== null) {
                $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            }

            $amount = 0;
            $orderId = null;

            if ($order !== null && $order->status === OrderStatus::AwaitingPayment) {
                $order->recalculateSubtotal();
                $amount = $order->subtotal;
                $orderId = $order->id;
            }

            $fee = 0;

            // Плата береться один раз, разом із підтвердженням: до вже підтвердженого бронювання
            // попереднє замовлення додається без неї (частина 2, п. 3.6)
            if ($pending) {
                $fee = $locked->fee_amount;
            }

            if ($amount + $fee === 0) {
                throw new BillChanged(__('payment.errors.nothing_due'));
            }

            $active = $locked->payments()->pendingOnline()->latest('id')->first();

            if ($active !== null) {
                if ($this->canReuse($active, $orderId, $amount, $fee)) {
                    return $active;
                }

                if ($active->status === PaymentStatus::Processing || ! $this->invalidatePending->handle($locked)) {
                    throw new PaymentException(__('payment.errors.in_progress'));
                }
            }

            $expiresAt = now()->addSeconds((int) config('seatly.payments.validity'));

            // Рахунок не має жити довше за саме бронювання: інакше гість сплатив би столик, який уже звільнено
            if ($pending && $locked->expires_at !== null && $locked->expires_at->lt($expiresAt)) {
                $expiresAt = $locked->expires_at;
            }

            $gateway = $this->gateways->default();

            $payment = new Payment([
                'order_id' => $orderId,
                'reservation_id' => $locked->id,
                'method' => PaymentMethod::Online,
                'provider' => $gateway->name(),
                'amount' => $amount,
                'tip_amount' => 0,
                'booking_fee_amount' => $fee,
                'status' => PaymentStatus::Created,
                'expires_at' => $expiresAt,
                'meta' => ['locale' => $locale],
            ]);
            $payment->rememberStatus(PaymentStatus::Created, now());
            $payment->save();

            try {
                $invoice = $gateway->createInvoice($payment);
            } catch (GatewayException $e) {
                report($e);

                throw new PaymentException(__('payment.errors.gateway_reservation'));
            }

            $payment->forceFill([
                'provider_invoice_id' => $invoice->invoiceId,
                'page_url' => $invoice->pageUrl,
            ])->save();

            return $payment;
        });
    }

    private function canReuse(Payment $active, ?int $orderId, int $amount, int $fee): bool
    {
        return $active->status === PaymentStatus::Created
            && $active->order_id === $orderId
            && $active->amount === $amount
            && $active->booking_fee_amount === $fee
            && $active->page_url !== null
            && $active->expires_at !== null
            && $active->expires_at->isFuture();
    }
}
