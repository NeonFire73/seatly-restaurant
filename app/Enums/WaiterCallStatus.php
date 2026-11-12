<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WaiterCallStatus: string implements HasLabel
{
    case New = 'new';
    case Done = 'done';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Новий',
            self::Done => 'Виконано',
        };
    }
}
