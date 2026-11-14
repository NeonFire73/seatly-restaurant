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
 * Повертає кошти за платежем цілком, разом із чайовими (частина 2, п. 6.6). Онлайн-платіж повертає
 * провайдер, офлайн-платіж (готівка, термінал) «повертається» лише записом у системі.
 */
class RefundPayment
{
    public function __construct(
        private GatewayManager $gateways,
        private ApplyPaymentStatus $applyStatus,
    ) {}

    /**
     * @param  User|null  $by  працівник, який повернув кошти; null — автоматичне повернення
     */
    public function handle(Payment $payment, ?User $by = null, ?string $reason = null): Payment
    {
        // Картка платежу в адмінці могла бути відкрита давно: рішення приймається за свіжим станом
        $payment->refresh();

        if ($payment->status !== PaymentStatus::Success) {
            throw new PaymentException('Повернути можна лише успішний платіж.');
        }

        $meta = $payment->meta ?? [];
        $meta['refund'] = [
            'by' => $by?->id,
            'by_name' => $by?->name,
            'reason' => $reason,
            'requested_at' => now()->toIso8601String(),
        ];

        if (! $payment->isOnline() || $payment->provider_invoice_id === null) {
            $payment->forceFill(['meta' => $meta])->save();
            $this->applyStatus->handle($payment, new PaymentStatusData('', PaymentStatus::Refunded));

            return $payment;
        }

        $gateway = $this->gateways->for($payment);

        try {
            $gateway->refund($payment);
            $payment->forceFill(['meta' => $meta])->save();
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
