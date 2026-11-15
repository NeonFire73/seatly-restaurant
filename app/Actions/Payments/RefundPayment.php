<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\Payments\GatewayException;
use App\Exceptions\Payments\PaymentException;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\GatewayManager;
use App\Services\Payments\PaymentStatusData;

/**
 * Повертає кошти за платежем (частина 2, п. 6.6). Типово — цілком, разом із чайовими. У платежі за бронювання
 * плата за бронювання й сума страв повертаються кожна за своїм правилом, тож їх можна повернути окремо (п. 3.6).
 * Онлайн-платіж повертає провайдер, офлайн-платіж (готівка, термінал) «повертається» лише записом у системі.
 */
class RefundPayment
{
    public const ALL = 'all';

    public const FEE = 'fee';

    public const ORDER = 'order';

    public function __construct(
        private GatewayManager $gateways,
        private ApplyPaymentStatus $applyStatus,
    ) {}

    /**
     * @param  User|null  $by  працівник, який повернув кошти; null — автоматичне повернення
     * @param  string  $part  що повертати: усе, лише плату за бронювання чи лише суму страв
     */
    public function handle(Payment $payment, ?User $by = null, ?string $reason = null, string $part = self::ALL): Payment
    {
        // Картка платежу в адмінці могла бути відкрита давно: рішення приймається за свіжим станом
        $payment->refresh();

        if ($payment->status !== PaymentStatus::Success) {
            throw new PaymentException('Повернути можна лише успішний платіж.');
        }

        $fee = 0;
        $order = 0;

        if ($part !== self::ORDER) {
            $fee = $payment->booking_fee_amount - $payment->refundedFee();
        }

        if ($part !== self::FEE) {
            $order = $payment->amount - $payment->refundedOrderAmount();
        }

        $amount = $fee + $order;

        // Чайові йдуть разом із рахунком: окремо від нього вони не повертаються
        if ($order > 0 && $payment->refundedOrderAmount() === 0) {
            $amount += $payment->tip_amount;
        }

        if ($amount <= 0) {
            throw new PaymentException('Цю частину платежу вже повернено.');
        }

        $amount = min($amount, $payment->refundable());
        $isFull = $amount === $payment->refundable();

        $meta = $payment->meta ?? [];
        $meta['refund'] = [
            'by' => $by?->id,
            'by_name' => $by?->name,
            'reason' => $reason,
            'requested_at' => now()->toIso8601String(),
        ];

        if (! $payment->isOnline() || $payment->provider_invoice_id === null) {
            $payment->forceFill(['meta' => $meta]);
            $payment->rememberRefundedParts($fee, $order);
            $payment->save();

            $data = new PaymentStatusData('', PaymentStatus::Refunded);

            if (! $isFull) {
                $data = new PaymentStatusData('', PaymentStatus::Success, null, null, $payment->refunded_amount + $amount);
            }

            $this->applyStatus->handle($payment, $data);

            return $payment;
        }

        $gateway = $this->gateways->for($payment);

        // Провайдерові сума потрібна лише для часткового повернення; без неї він повертає весь платіж
        $requested = $amount;

        if ($isFull && $payment->refunded_amount === 0) {
            $requested = null;
        }

        try {
            $gateway->refund($payment, $requested);
            $payment->forceFill(['meta' => $meta]);
            $payment->rememberRefundedParts($fee, $order);
            $payment->save();
            // Банк може виконувати повернення не одразу: тоді статус зміниться пізніше, вебхуком
            $data = $gateway->getStatus($payment);
        } catch (GatewayException $e) {
            report($e);

            throw new PaymentException('Провайдер не прийняв повернення: '.$e->getMessage());
        }

        $this->applyStatus->handle($payment, $data);

        return $payment;
    }
}
