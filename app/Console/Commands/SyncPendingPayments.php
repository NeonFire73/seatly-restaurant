<?php

namespace App\Console\Commands;

use App\Actions\Payments\SyncPaymentStatus;
use App\Exceptions\Payments\GatewayException;
use App\Models\Payment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Фонова задача (частина 2, п. 6.3 і розділ 8): страховка на випадок загубленого вебхука. Крім того,
 * лише так система дізнається про прострочений рахунок — про нього Monobank вебхуком не повідомляє.
 */
#[Signature('payments:sync')]
#[Description('Запитує в провайдера статус онлайн-платежів, які зависли неоплаченими')]
class SyncPendingPayments extends Command
{
    public function handle(SyncPaymentStatus $syncStatus): int
    {
        $payments = Payment::query()
            ->pendingOnline()
            ->whereNotNull('provider_invoice_id')
            ->where('created_at', '<=', now()->subSeconds((int) config('seatly.payments.stale_after')))
            ->get();

        $failed = 0;

        foreach ($payments as $payment) {
            try {
                $syncStatus->handle($payment);
            } catch (GatewayException $e) {
                // Один недоступний рахунок не має зупиняти перевірку решти
                report($e);
                $failed++;
            }
        }

        $this->info("Перевірено платежів: {$payments->count()}, без відповіді провайдера: {$failed}");

        return self::SUCCESS;
    }
}
