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
