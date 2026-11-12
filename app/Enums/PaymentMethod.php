<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Online = 'online';
    case Cash = 'cash';
    case Terminal = 'terminal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Online => 'Онлайн',
            self::Cash => 'Готівка',
            self::Terminal => 'Термінал',
        };
    }
}
