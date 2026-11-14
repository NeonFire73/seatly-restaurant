<?php

namespace App\Actions\Payments;

use App\Exceptions\Payments\GatewayException;
use App\Models\Payment;
use App\Services\Payments\GatewayManager;

/**
 * Запитує стан платежу в провайдера — страховка на випадок, коли вебхук загубився чи ще не дійшов
 * (частина 2, п. 6.3). Про прострочення рахунку Monobank вебхуком не повідомляє взагалі.
 */
class SyncPaymentStatus
{
    public function __construct(
        private GatewayManager $gateways,
        private ApplyPaymentStatus $applyStatus,
    ) {}

    /**
     * @throws GatewayException
     */
    public function handle(Payment $payment): Payment
    {
        if (! $payment->isOnline() || $payment->provider === null || $payment->provider_invoice_id === null) {
            return $payment;
        }

        $data = $this->gateways->for($payment)->getStatus($payment);
        $this->applyStatus->handle($payment, $data);

        return $payment;
    }
}
