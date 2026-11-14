<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\GatewayException;
use App\Exceptions\Payments\InvalidWebhookSignature;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Контракт платіжного провайдера (частина 2, п. 6.1). Заміна провайдера — це новий клас із цим інтерфейсом.
 * Будь-яка помилка зв'язку чи відмова провайдера — GatewayException.
 */
interface PaymentGateway
{
    /**
     * Назва для поля payments.provider.
     */
    public function name(): string;

    /**
     * @throws GatewayException
     */
    public function createInvoice(Payment $payment): InvoiceData;

    /**
     * @throws GatewayException
     */
    public function getStatus(Payment $payment): PaymentStatusData;

    /**
     * Робить неоплачений рахунок недійсним.
     *
     * @throws GatewayException
     */
    public function invalidate(Payment $payment): void;

    /**
     * @param  int|null  $amount  сума в копійках; без неї повертається весь платіж
     *
     * @throws GatewayException
     */
    public function refund(Payment $payment, ?int $amount = null): void;

    /**
     * @throws InvalidWebhookSignature
     * @throws GatewayException
     */
    public function parseWebhook(Request $request): PaymentStatusData;
}
