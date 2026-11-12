<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrderType: string implements HasLabel
{
    case DineIn = 'dine_in';
    case Preorder = 'preorder';

    public function getLabel(): string
    {
        return match ($this) {
            self::DineIn => 'У залі',
            self::Preorder => 'Попереднє',
        };
    }
}
