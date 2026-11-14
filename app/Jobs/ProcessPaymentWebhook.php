<?php

namespace App\Jobs;

use App\Actions\Payments\HandlePaymentWebhook;
use App\Models\PaymentWebhookLog;
use App\Services\Payments\PaymentStatusData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Обробка вебхука в черзі (частина 2, п. 6.4): маршрут лише перевіряє підпис і відразу відповідає провайдеру.
 */
class ProcessPaymentWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public int $logId,
        public PaymentStatusData $data,
    ) {}

    public function handle(HandlePaymentWebhook $handleWebhook): void
    {
        $handleWebhook->handle($this->data, PaymentWebhookLog::find($this->logId));
    }

    public function failed(Throwable $e): void
    {
        PaymentWebhookLog::whereKey($this->logId)->update(['error' => mb_substr($e->getMessage(), 0, 500)]);
    }
}
