<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Allergen: string implements HasLabel
{
    case Gluten = 'gluten';
    case Crustaceans = 'crustaceans';
    case Eggs = 'eggs';
    case Fish = 'fish';
    case Peanuts = 'peanuts';
    case Soy = 'soy';
    case Milk = 'milk';
    case Nuts = 'nuts';
    case Celery = 'celery';
    case Mustard = 'mustard';
    case Sesame = 'sesame';
    case Sulphites = 'sulphites';
    case Lupin = 'lupin';
    case Molluscs = 'molluscs';

    public function getLabel(): string
    {
        return match ($this) {
            self::Gluten => 'Глютен',
            self::Crustaceans => 'Ракоподібні',
            self::Eggs => 'Яйця',
            self::Fish => 'Риба',
            self::Peanuts => 'Арахіс',
            self::Soy => 'Соя',
            self::Milk => 'Молоко',
            self::Nuts => 'Горіхи',
            self::Celery => 'Селера',
            self::Mustard => 'Гірчиця',
            self::Sesame => 'Кунжут',
            self::Sulphites => 'Сульфіти',
            self::Lupin => 'Люпин',
            self::Molluscs => 'Молюски',
        };
    }
}
