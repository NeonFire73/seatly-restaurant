<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReservationSource: string implements HasLabel
{
    case Site = 'site';
    case Admin = 'admin';
    case Phone = 'phone';

    public function getLabel(): string
    {
        return match ($this) {
            self::Site => 'Сайт',
            self::Admin => 'Адмінка',
            self::Phone => 'Телефон',
        };
    }
}
