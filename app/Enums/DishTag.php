<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DishTag: string implements HasLabel
{
    case Vegetarian = 'vegetarian';
    case Vegan = 'vegan';
    case Spicy = 'spicy';
    case GlutenFree = 'gluten_free';

    public function getLabel(): string
    {
        return match ($this) {
            self::Vegetarian => 'Вегетаріанська',
            self::Vegan => 'Веганська',
            self::Spicy => 'Гостра',
            self::GlutenFree => 'Без глютену',
        };
    }
}
