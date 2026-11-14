<?php

namespace App\Filament\Resources\PaymentWebhookLogs\Pages;

use App\Filament\Resources\PaymentWebhookLogs\PaymentWebhookLogResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentWebhookLogs extends ListRecords
{
    protected static string $resource = PaymentWebhookLogResource::class;
}
