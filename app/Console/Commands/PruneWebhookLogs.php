<?php

namespace App\Console\Commands;

use App\Models\PaymentWebhookLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Фонова задача (частина 2, розділ 8): журнал вебхуків потрібен для розбору свіжих платежів, а не назавжди.
 */
#[Signature('payments:prune-webhook-logs')]
#[Description('Видаляє старі записи журналу вебхуків')]
class PruneWebhookLogs extends Command
{
    public function handle(): int
    {
        $days = (int) config('seatly.payments.webhook_log_days');
        $deleted = PaymentWebhookLog::where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Видалено записів: {$deleted}");

        return self::SUCCESS;
    }
}
