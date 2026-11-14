<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Services\Payments\PaymentStatusData;

/**
 * Обробляє перевірений вебхук (частина 2, п. 6.2): знаходить платіж за рахунком провайдера
 * й застосовує новий стан. Підставна платіжна сторінка викликає цю саму дію.
 */
class HandlePaymentWebhook
{
    public function __construct(private ApplyPaymentStatus $applyStatus) {}

    public function handle(PaymentStatusData $data, ?PaymentWebhookLog $log = null): ?Payment
    {
        $payment = Payment::where('provider_invoice_id', $data->invoiceId)->first();

        if ($payment === null) {
            $log?->update(['processed_at' => now(), 'error' => 'Платіж із таким рахунком не знайдено']);

            return null;
        }

        $this->applyStatus->handle($payment, $data);
        $log?->update(['processed_at' => now()]);

        return $payment;
    }
}
