<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Notifications\StaffNotifier;
use App\Services\Payments\PaymentStatusData;
use Illuminate\Support\Facades\DB;

/**
 * Єдине місце, де змінюється статус платежу (частина 2, п. 6.3). Дані — лише від провайдера:
 * з вебхука або із запиту статусу. Повторний чи запізнілий стан нічого не змінює.
 */
class ApplyPaymentStatus
{
    /**
     * @return bool чи змінився статус платежу
     */
    public function handle(Payment $payment, PaymentStatusData $data): bool
    {
        $changed = DB::transaction(function () use ($payment, $data): bool {
            // Порядок блокувань той самий, що й у StartPayment: спершу замовлення, потім платіж — інакше взаємне блокування
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $this->isNews($locked, $data)) {
                return false;
            }

            $previous = $locked->status;
            $at = $data->modifiedAt ?? now()->toImmutable();

            $locked->status = $data->status;
            $locked->provider_modified_at = $at;
            $locked->refunded_amount = $data->refundedAmount;

            if ($data->failureReason !== null) {
                $locked->failure_reason = $data->failureReason;
            }

            if ($data->status === PaymentStatus::Success && $locked->paid_at === null) {
                $locked->paid_at = $at;
            }

            if ($data->status === PaymentStatus::Refunded) {
                $locked->refunded_at ??= $at;
                // Повертається платіж цілком, разом із чайовими (частина 2, розділ 5)
                $locked->refunded_amount = $locked->total();
            }

            if ($previous !== $data->status) {
                $locked->rememberStatus($data->status, $at);
            }

            $locked->save();
            $order->recalculatePaidTotal();

            return $previous !== $data->status;
        });

        $payment->refresh();

        if ($changed && $payment->status === PaymentStatus::Success && $payment->isOnline()) {
            StaffNotifier::orderPaid($payment);
        }

        return $changed;
    }

    /**
     * Вебхуки приходять повторно й не за порядком. Стан застосовується, якщо він не старіший за збережений
     * і не відкочує платіж назад; за однакового часу (Monobank дає його з точністю до секунди) — лише вперед.
     */
    private function isNews(Payment $payment, PaymentStatusData $data): bool
    {
        $currentRank = $payment->status->rank();
        $newRank = $data->status->rank();

        if ($newRank < $currentRank) {
            return false;
        }

        if ($data->status === $payment->status) {
            // Той самий статус — новина лише тоді, коли змінилася сума часткового повернення
            if ($data->status === PaymentStatus::Refunded || $data->refundedAmount === $payment->refunded_amount) {
                return false;
            }
        }

        $stored = $payment->provider_modified_at;

        if ($stored === null || $data->modifiedAt === null) {
            return true;
        }

        if ($data->modifiedAt->lt($stored)) {
            return false;
        }

        if ($data->modifiedAt->eq($stored)) {
            return $newRank > $currentRank;
        }

        return true;
    }
}
