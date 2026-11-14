<?php

namespace App\Services\Payments;

use App\Models\Payment;
use InvalidArgumentException;

/**
 * Обирає шлюз: для нових платежів — із конфігурації (PAYMENT_GATEWAY), для наявних — той, через який
 * платіж створено. Інакше після перемикання fake → monobank старі демо-платежі пішли б у справжній банк.
 */
class GatewayManager
{
    public function default(): PaymentGateway
    {
        return $this->driver((string) config('seatly.payments.gateway'));
    }

    public function for(Payment $payment): PaymentGateway
    {
        return $this->driver((string) $payment->provider);
    }

    public function driver(string $name): PaymentGateway
    {
        return match ($name) {
            MonobankGateway::NAME => app(MonobankGateway::class),
            FakeGateway::NAME => app(FakeGateway::class),
            default => throw new InvalidArgumentException("Невідомий платіжний шлюз: {$name}"),
        };
    }

    public function isFake(): bool
    {
        return config('seatly.payments.gateway') === FakeGateway::NAME;
    }
}
