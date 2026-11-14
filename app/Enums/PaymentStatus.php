<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasLabel
{
    case Created = 'created';
    case Processing = 'processing';
    case Success = 'success';
    case Failure = 'failure';
    case Expired = 'expired';
    case Refunded = 'refunded';

    /**
     * Гість ще може заплатити або оплата в дорозі: такий платіж вважається активним (частина 2, п. 6.3).
     */
    public function isPending(): bool
    {
        return in_array($this, [self::Created, self::Processing], true);
    }

    /**
     * Наскільки далеко платіж просунувся. Статус ніколи не повертається на нижчий щабель,
     * хоч би в якому порядку прийшли вебхуки.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Created => 0,
            self::Processing => 1,
            self::Success, self::Failure, self::Expired => 2,
            self::Refunded => 3,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => 'Створено',
            self::Processing => 'Обробляється',
            self::Success => 'Успішно',
            self::Failure => 'Відхилено',
            self::Expired => 'Прострочено',
            self::Refunded => 'Повернено',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Created => 'gray',
            self::Processing => 'warning',
            self::Success => 'success',
            self::Failure => 'danger',
            self::Expired => 'gray',
            self::Refunded => 'info',
        };
    }
}
