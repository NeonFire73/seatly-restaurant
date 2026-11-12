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
