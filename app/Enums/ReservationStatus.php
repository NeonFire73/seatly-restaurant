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

    /**
     * Статуси, у яких бронювання займає столик (частина 2, п. 3.4).
     *
     * @return list<self>
     */
    public static function live(): array
    {
        return [self::PendingPayment, self::Confirmed, self::Seated];
    }

    public function occupiesTable(): bool
    {
        return in_array($this, self::live(), true);
    }

    public function canTransitionTo(self $next): bool
    {
        $allowed = match ($this) {
            self::PendingPayment => [self::Confirmed, self::Expired, self::Cancelled],
            self::Confirmed => [self::Seated, self::Cancelled, self::NoShow],
            self::Seated => [self::Completed],
            // Менеджер може виправити помилкову позначку «не прийшов»
            self::NoShow => [self::Confirmed],
            self::Completed, self::Cancelled, self::Expired => [],
        };

        return in_array($next, $allowed, true);
    }

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
