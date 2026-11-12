<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderItemStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Cooking = 'cooking';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Нова',
            self::Cooking => 'Готується',
            self::Ready => 'Готово',
            self::Served => 'Подано',
            self::Cancelled => 'Скасовано',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'gray',
            self::Cooking => 'warning',
            self::Ready => 'info',
            self::Served => 'success',
            self::Cancelled => 'danger',
        };
    }
}
