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

    /**
     * Позиція рухається лише вперед: нова → готується → готово → подано. Крок можна пропустити
     * (напій подають одразу, без кухні), а скасувати можна будь-яку ще не скасовану позицію.
     */
    public function canTransitionTo(self $status): bool
    {
        if ($this === self::Cancelled || $this === $status) {
            return false;
        }

        if ($status === self::Cancelled) {
            return true;
        }

        return $status->step() > $this->step();
    }

    private function step(): int
    {
        return match ($this) {
            self::New => 0,
            self::Cooking => 1,
            self::Ready => 2,
            self::Served => 3,
            self::Cancelled => 4,
        };
    }

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
