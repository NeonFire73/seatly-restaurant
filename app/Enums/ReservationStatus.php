<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReservationStatus: string implements HasColor, HasLabel
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Seated = 'seated';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingPayment => 'Очікує оплати',
            self::Confirmed => 'Підтверджено',
            self::Seated => 'Гості за столиком',
            self::Completed => 'Завершено',
            self::Cancelled => 'Скасовано',
            self::NoShow => 'Не прийшов',
            self::Expired => 'Прострочено',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PendingPayment => 'warning',
            self::Confirmed => 'info',
            self::Seated => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'gray',
            self::NoShow => 'danger',
            self::Expired => 'gray',
        };
    }
}
