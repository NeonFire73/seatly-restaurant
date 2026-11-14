<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\Payments\GatewayException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\GatewayManager;
use App\Services\Payments\PaymentStatusData;

/**
 * Робить недійсними неоплачені онлайн-рахунки замовлення: коли гість починає оплату з іншою сумою,
 * коли рахунок сплатили готівкою чи замовлення скасували. Інакше той самий рахунок могли б сплатити двічі.
 */
class InvalidatePendingPayments
{
    public function __construct(
        private GatewayManager $gateways,
        private ApplyPaymentStatus $applyStatus,
        private SyncPaymentStatus $syncStatus,
    ) {}

    /**
     * @return bool false — принаймні один рахунок скасувати не вдалося (найімовірніше, його саме оплачують)
     */
    public function handle(Order $order): bool
    {
        $allInvalidated = true;

        foreach ($order->payments()->pendingOnline()->get() as $payment) {
            if (! $this->invalidate($payment)) {
                $allInvalidated = false;
            }
        }

        return $allInvalidated;
    }

    private function invalidate(Payment $payment): bool
    {
        if ($payment->provider_invoice_id === null) {
            return false;
        }

        try {
            $this->gateways->for($payment)->invalidate($payment);
        } catch (GatewayException $e) {
            report($e);

            // Провайдер відмовив — можливо, рахунок щойно сплатили. Правду покаже запит статусу
            try {
                $this->syncStatus->handle($payment);
            } catch (GatewayException $e) {
                report($e);
            }

            return ! $payment->refresh()->status->isPending();
        }

        $this->applyStatus->handle($payment, new PaymentStatusData($payment->provider_invoice_id, PaymentStatus::Expired));

        return true;
    }
}
