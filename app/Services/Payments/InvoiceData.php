<?php

namespace App\Services\Payments;

/**
 * Рахунок, створений у провайдера: його ідентифікатор і адреса платіжної сторінки.
 */
final readonly class InvoiceData
{
    public function __construct(
        public string $invoiceId,
        public string $pageUrl,
    ) {}
}
