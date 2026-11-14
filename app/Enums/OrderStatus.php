<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case AwaitingPayment = 'awaiting_payment';
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * Дозволені переходи (частина 2, п. 4.1); закрите й скасоване замовлення більше не змінюється.
     */
    public function canTransitionTo(self $status): bool
    {
        return match ($this) {
            self::AwaitingPayment => in_array($status, [self::Open, self::Cancelled], true),
            self::Open => in_array($status, [self::Closed, self::Cancelled], true),
            self::Closed, self::Cancelled => false,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Очікує оплати',
            self::Open => 'Відкрите',
            self::Closed => 'Закрите',
            self::Cancelled => 'Скасоване',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'warning',
            self::Open => 'info',
            self::Closed => 'success',
            self::Cancelled => 'gray',
        };
    }
}
