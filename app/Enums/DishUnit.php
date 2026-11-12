<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DishUnit: string implements HasLabel
{
    case Gram = 'g';
    case Milliliter = 'ml';

    public function getLabel(): string
    {
        return match ($this) {
            self::Gram => 'г',
            self::Milliliter => 'мл',
        };
    }
}
