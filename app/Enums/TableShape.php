<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TableShape: string implements HasLabel
{
    case Round = 'round';
    case Square = 'square';
    case Rect = 'rect';

    public function getLabel(): string
    {
        return match ($this) {
            self::Round => 'Круглий',
            self::Square => 'Квадратний',
            self::Rect => 'Прямокутний',
        };
    }
}
