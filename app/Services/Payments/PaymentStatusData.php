<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;

/**
 * Стан рахунку за даними провайдера — з вебхука або із запиту статусу.
 */
final readonly class PaymentStatusData
{
    /**
     * @param  CarbonImmutable|null  $modifiedAt  час зміни в провайдера; за ним відкидаються запізнілі вебхуки
     * @param  int  $refundedAmount  скільки вже повернено, у копійках
     */
    public function __construct(
        public string $invoiceId,
        public PaymentStatus $status,
        public ?CarbonImmutable $modifiedAt = null,
        public ?string $failureReason = null,
        public int $refundedAmount = 0,
    ) {}
}
