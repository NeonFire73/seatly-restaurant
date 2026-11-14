<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\Payments\GatewayException;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Підставний шлюз (частина 2, п. 6.5): для тестів, розробки без тунелю й демо. Замість сторінки Monobank
 * відкривається локальна сторінка з кнопками, а «стан рахунку в провайдера» лежить у кеші.
 */
class FakeGateway implements PaymentGateway
{
    public const NAME = 'fake';

    public function name(): string
    {
        return self::NAME;
    }

    public function createInvoice(Payment $payment): InvoiceData
    {
        return new InvoiceData(
            'fake_'.strtolower($payment->id),
            route('fake-pay.show', ['payment' => $payment->id]),
        );
    }

    public function getStatus(Payment $payment): PaymentStatusData
    {
        $invoiceId = (string) $payment->provider_invoice_id;
        $state = Cache::get($this->cacheKey($invoiceId));

        if (is_array($state)) {
            return new PaymentStatusData(
                $invoiceId,
                PaymentStatus::from($state['status']),
                CarbonImmutable::parse($state['at']),
                $state['reason'] ?? null,
            );
        }

        // Гість не натиснув жодної кнопки: рахунок чекає, доки не сплине його строк
        if ($payment->status->isPending() && $payment->expires_at !== null && $payment->expires_at->isPast()) {
            return new PaymentStatusData($invoiceId, PaymentStatus::Expired, CarbonImmutable::instance($payment->expires_at));
        }

        $modifiedAt = null;

        if ($payment->provider_modified_at !== null) {
            $modifiedAt = CarbonImmutable::instance($payment->provider_modified_at);
        }

        return new PaymentStatusData($invoiceId, $payment->status, $modifiedAt);
    }

    public function invalidate(Payment $payment): void
    {
        if ($this->getStatus($payment)->status === PaymentStatus::Success) {
            throw new GatewayException('Рахунок уже сплачено, зробити його недійсним не можна.');
        }

        $this->simulate($payment, PaymentStatus::Expired);
    }

    public function refund(Payment $payment, ?int $amount = null): void
    {
        $this->simulate($payment, PaymentStatus::Refunded);
    }

    public function parseWebhook(Request $request): PaymentStatusData
    {
        $status = PaymentStatus::tryFrom((string) $request->input('status'));
        $invoiceId = (string) $request->input('invoiceId');

        if ($status === null || $invoiceId === '') {
            throw new GatewayException('Некоректні дані підставного вебхука.');
        }

        return new PaymentStatusData($invoiceId, $status, CarbonImmutable::parse($request->input('modifiedDate', 'now')));
    }

    /**
     * Те, що на справжньому шлюзі робить гість на сторінці банку: змінює стан рахунку «в провайдера».
     * Результат має пройти через той самий обробник, що й вебхук.
     */
    public function simulate(Payment $payment, PaymentStatus $status): PaymentStatusData
    {
        $invoiceId = (string) $payment->provider_invoice_id;
        $at = CarbonImmutable::now();
        $reason = null;

        if ($status === PaymentStatus::Failure) {
            $reason = 'Платіж відхилено на підставній платіжній сторінці';
        }

        // Масив, а не об'єкт: кеш Laravel 13 об'єктів не розсеріалізовує
        Cache::put($this->cacheKey($invoiceId), ['status' => $status->value, 'at' => $at->toIso8601String(), 'reason' => $reason], now()->addDay());

        return new PaymentStatusData($invoiceId, $status, $at, $reason);
    }

    private function cacheKey(string $invoiceId): string
    {
        return 'fake-gateway.'.$invoiceId;
    }
}
