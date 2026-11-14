<?php

namespace App\Actions\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\Payments\BillChanged;
use App\Exceptions\Payments\GatewayException;
use App\Exceptions\Payments\PaymentException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\GatewayManager;
use App\Services\Payments\TipSettings;
use App\Support\Localization;
use Illuminate\Support\Facades\DB;

/**
 * Починає онлайн-оплату замовлення (частина 2, пп. 6.2 і 6.3): фіксує суму до сплати й чайові,
 * створює платіж і рахунок у провайдера. Усі суми рахуються тут, на сервері; від гостя приходить
 * лише бажаний розмір чайових і сума, яку він бачив на екрані.
 */
class StartPayment
{
    public function __construct(
        private GatewayManager $gateways,
        private InvalidatePendingPayments $invalidatePending,
    ) {}

    /**
     * @param  int  $tipAmount  чайові в копійках
     * @param  int|null  $expectedDue  сума до сплати, яку гість бачив; якщо вона вже інша — оплата не починається
     */
    public function handle(Order $order, int $tipAmount = 0, ?int $expectedDue = null, ?string $locale = null): Payment
    {
        if (! Localization::isSupported($locale)) {
            $locale = app()->getLocale();
        }

        return DB::transaction(function () use ($order, $tipAmount, $expectedDue, $locale): Payment {
            // Блокування замовлення ставить у чергу двох гостей, які одночасно натиснули «Оплатити» за одним QR
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Open) {
                throw new PaymentException(__('payment.errors.order_closed'));
            }

            $locked->recalculateSubtotal();
            $locked->recalculatePaidTotal();
            $due = $locked->amountDue();

            if ($due === 0) {
                throw new BillChanged(__('payment.errors.nothing_due'));
            }

            if ($expectedDue !== null && $expectedDue !== $due) {
                throw new BillChanged(__('payment.errors.bill_changed'));
            }

            if ($tipAmount < 0 || $tipAmount > TipSettings::maxFor($due)) {
                throw new PaymentException(__('payment.errors.tip_too_big'));
            }

            $active = $locked->payments()->pendingOnline()->latest('id')->first();

            if ($active !== null) {
                if ($this->canReuse($active, $due, $tipAmount)) {
                    return $active;
                }

                // Хтось уже вводить дані картки: другий рахунок означав би подвійну оплату
                if ($active->status === PaymentStatus::Processing || ! $this->invalidatePending->handle($locked)) {
                    throw new PaymentException(__('payment.errors.in_progress'));
                }
            }

            $gateway = $this->gateways->default();

            $payment = new Payment([
                'method' => PaymentMethod::Online,
                'provider' => $gateway->name(),
                'amount' => $due,
                'tip_amount' => $tipAmount,
                'status' => PaymentStatus::Created,
                // Чайові отримує офіціант замовлення на момент оплати (частина 2, розділ 5)
                'waiter_id' => $locked->waiter_id,
                'expires_at' => now()->addSeconds((int) config('seatly.payments.validity')),
                'meta' => ['locale' => $locale],
            ]);
            $payment->rememberStatus(PaymentStatus::Created, now());
            $locked->payments()->save($payment);

            try {
                $invoice = $gateway->createInvoice($payment);
            } catch (GatewayException $e) {
                report($e);

                // Транзакція відкочується разом із платежем: рахунку в провайдера немає, сліду в базі теж
                throw new PaymentException(__('payment.errors.gateway'));
            }

            $payment->forceFill([
                'provider_invoice_id' => $invoice->invoiceId,
                'page_url' => $invoice->pageUrl,
            ])->save();

            return $payment;
        });
    }

    /**
     * Той самий рахунок із тими самими чайовими: гість просто повернувся до оплати, новий рахунок не потрібен.
     */
    private function canReuse(Payment $active, int $due, int $tipAmount): bool
    {
        return $active->status === PaymentStatus::Created
            && $active->amount === $due
            && $active->tip_amount === $tipAmount
            && $active->page_url !== null
            && $active->expires_at !== null
            && $active->expires_at->isFuture();
    }
}
