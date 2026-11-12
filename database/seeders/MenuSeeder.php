<?php

namespace Database\Seeders;

use App\Enums\Allergen;
use App\Enums\DishTag;
use App\Enums\DishUnit;
use App\Models\MenuCategory;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->menu() as $sort => $category) {
            $model = MenuCategory::create([
                'name' => $category['name'],
                'slug' => $category['slug'],
                'sort' => $sort + 1,
            ]);

            foreach ($category['dishes'] as $dishSort => $dish) {
                $model->dishes()->create([
                    'name' => ['uk' => $dish[0], 'en' => $dish[1]],
                    'description' => ['uk' => $dish[2], 'en' => $dish[3]],
                    'price' => $dish[4] * 100,
                    'weight' => $dish[5],
                    'unit' => $category['unit'],
                    'cook_time_minutes' => $dish[6],
                    'tags' => $dish[7] ?? [],
                    'allergens' => $dish[8] ?? [],
                    'is_popular' => $dish[9] ?? false,
                    // Напої й десерти не готують заздалегідь до приходу гостя
                    'is_preorderable' => $category['preorderable'],
                    'sort' => $dishSort + 1,
                ]);
            }
        }
    }

    /**
     * Страва: [назва uk, назва en, опис uk, опис en, ціна в гривнях, вихід, хвилин приготування, позначки, алергени, популярна].
     *
     * @return list<array{name: array<string, string>, slug: string, unit: DishUnit, preorderable: bool, dishes: list<array<int, mixed>>}>
     */
    private function menu(): array
    {
        return [
            [
                'name' => ['uk' => 'Закуски', 'en' => 'Starters'],
                'slug' => 'starters',
                'unit' => DishUnit::Gram,
                'preorderable' => true,
                'dishes' => [
                    ['Тартар із тунця', 'Tuna tartare', 'Тунець, авокадо, кунжутна заправка, чіпси з васабі.', 'Tuna, avocado, sesame dressing, wasabi crisps.', 385, 160, 10, [], [Allergen::Fish, Allergen::Sesame, Allergen::Soy], true],
                    ['Брускети з томатами', 'Tomato bruschetta', 'Хліб на заквасці, томати, базилік, оливкова олія.', 'Sourdough bread, tomatoes, basil, olive oil.', 165, 180, 8, [DishTag::Vegan], [Allergen::Gluten]],
                    ['Мідії в білому вині', 'Mussels in white wine', 'Мідії, вершки, часник, біле вино, грінки.', 'Mussels, cream, garlic, white wine, croutons.', 320, 450, 15, [], [Allergen::Molluscs, Allergen::Milk, Allergen::Gluten, Allergen::Sulphites], true],
                    ['Сирна тарілка', 'Cheese board', 'Чотири види сиру, мед, горіхи, виноград.', 'Four cheeses, honey, nuts, grapes.', 410, 250, 5, [DishTag::Vegetarian], [Allergen::Milk, Allergen::Nuts]],
                    ['Хумус із печеним перцем', 'Hummus with roasted pepper', 'Хумус, печений перець, піта.', 'Hummus, roasted pepper, pita.', 175, 220, 7, [DishTag::Vegan], [Allergen::Sesame, Allergen::Gluten]],
                    ['Креветки темпура', 'Tempura shrimp', 'Тигрові креветки в клярі, солодкий чилі-соус.', 'Tiger shrimp in batter, sweet chili sauce.', 340, 180, 12, [DishTag::Spicy], [Allergen::Crustaceans, Allergen::Gluten, Allergen::Eggs]],
                    ['Оселедець із картоплею', 'Herring with potatoes', 'Оселедець власного засолу, молода картопля, цибуля.', 'House-cured herring, baby potatoes, onion.', 185, 250, 8, [DishTag::GlutenFree], [Allergen::Fish]],
                ],
            ],
            [
                'name' => ['uk' => 'Салати', 'en' => 'Salads'],
                'slug' => 'salads',
                'unit' => DishUnit::Gram,
                'preorderable' => true,
                'dishes' => [
                    ['Цезар із куркою', 'Chicken Caesar', 'Ромен, куряче філе гриль, пармезан, грінки, соус цезар.', 'Romaine, grilled chicken, parmesan, croutons, Caesar dressing.', 265, 280, 12, [], [Allergen::Gluten, Allergen::Milk, Allergen::Eggs, Allergen::Fish], true],
                    ['Салат із печеним буряком', 'Roasted beetroot salad', 'Буряк, козячий сир, рукола, волоський горіх.', 'Beetroot, goat cheese, arugula, walnuts.', 210, 230, 10, [DishTag::Vegetarian, DishTag::GlutenFree], [Allergen::Milk, Allergen::Nuts]],
                    ['Грецький салат', 'Greek salad', 'Томати, огірок, перець, оливки, фета.', 'Tomatoes, cucumber, pepper, olives, feta.', 220, 300, 8, [DishTag::Vegetarian, DishTag::GlutenFree], [Allergen::Milk]],
                    ['Салат із лососем', 'Salmon salad', 'Лосось слабкого соління, мікс салатів, авокадо, цитрусова заправка.', 'Lightly salted salmon, mixed greens, avocado, citrus dressing.', 345, 250, 10, [DishTag::GlutenFree], [Allergen::Fish, Allergen::Mustard]],
                    ['Теплий салат із телятиною', 'Warm veal salad', 'Телятина, печені овочі, соус терияки.', 'Veal, roasted vegetables, teriyaki sauce.', 315, 260, 15, [], [Allergen::Soy, Allergen::Sesame]],
                    ['Боул з кіноа', 'Quinoa bowl', 'Кіноа, нут, авокадо, едамаме, тахіні.', 'Quinoa, chickpeas, avocado, edamame, tahini.', 245, 320, 10, [DishTag::Vegan, DishTag::GlutenFree], [Allergen::Sesame, Allergen::Soy]],
                ],
            ],
            [
                'name' => ['uk' => 'Супи', 'en' => 'Soups'],
                'slug' => 'soups',
                'unit' => DishUnit::Gram,
                'preorderable' => true,
                'dishes' => [
                    ['Борщ із пампушками', 'Borscht with garlic buns', 'Червоний борщ на яловичому бульйоні, сметана, пампушки з часником.', 'Red borscht on beef broth, sour cream, garlic buns.', 185, 400, 10, [], [Allergen::Gluten, Allergen::Milk, Allergen::Celery], true],
                    ['Юшка з морської риби', 'Seafood fish soup', 'Судак, лосось, мідії, томатний бульйон.', 'Pike-perch, salmon, mussels, tomato broth.', 295, 350, 15, [DishTag::GlutenFree], [Allergen::Fish, Allergen::Molluscs, Allergen::Celery]],
                    ['Крем-суп із гарбуза', 'Pumpkin cream soup', 'Гарбуз, кокосове молоко, гарбузове насіння.', 'Pumpkin, coconut milk, pumpkin seeds.', 160, 300, 8, [DishTag::Vegan, DishTag::GlutenFree], []],
                    ['Курячий бульйон із локшиною', 'Chicken noodle soup', 'Домашня локшина, курка, зелень.', 'Homemade noodles, chicken, herbs.', 145, 350, 8, [], [Allergen::Gluten, Allergen::Eggs, Allergen::Celery]],
                    ['Том ям', 'Tom yum', 'Гострий суп із креветками, грибами й кокосовим молоком.', 'Spicy soup with shrimp, mushrooms and coconut milk.', 310, 350, 15, [DishTag::Spicy, DishTag::GlutenFree], [Allergen::Crustaceans, Allergen::Fish]],
                ],
            ],
            [
                'name' => ['uk' => 'Основні страви', 'en' => 'Main courses'],
                'slug' => 'mains',
                'unit' => DishUnit::Gram,
                'preorderable' => true,
                'dishes' => [
                    ['Стейк рібай', 'Ribeye steak', 'Мармурова яловичина, соус демігляс, печений часник.', 'Marbled beef, demi-glace, roasted garlic.', 890, 300, 25, [DishTag::GlutenFree], [], true],
                    ['Дорадо на грилі', 'Grilled dorado', 'Ціла дорадо, лимон, овочі гриль.', 'Whole dorado, lemon, grilled vegetables.', 520, 400, 25, [DishTag::GlutenFree], [Allergen::Fish], true],
                    ['Лосось із булгуром', 'Salmon with bulgur', 'Філе лосося, булгур, шпинат, вершковий соус.', 'Salmon fillet, bulgur, spinach, cream sauce.', 485, 330, 20, [], [Allergen::Fish, Allergen::Gluten, Allergen::Milk]],
                    ['Качина ніжка конфі', 'Duck leg confit', 'Качина ніжка, пюре з селери, вишневий соус.', 'Duck leg, celery purée, cherry sauce.', 465, 320, 25, [DishTag::GlutenFree], [Allergen::Celery, Allergen::Milk]],
                    ['Вареники з картоплею', 'Potato varenyky', 'Вареники з картоплею й грибами, смажена цибуля, сметана.', 'Dumplings with potato and mushrooms, fried onion, sour cream.', 175, 300, 12, [DishTag::Vegetarian], [Allergen::Gluten, Allergen::Milk, Allergen::Eggs]],
                    ['Паста з морепродуктами', 'Seafood pasta', 'Лінгвіні, креветки, мідії, кальмар, томатний соус.', 'Linguine, shrimp, mussels, squid, tomato sauce.', 395, 350, 18, [], [Allergen::Gluten, Allergen::Crustaceans, Allergen::Molluscs]],
                    ['Різото з грибами', 'Mushroom risotto', 'Арборіо, білі гриби, пармезан, трюфельна олія.', 'Arborio, porcini, parmesan, truffle oil.', 330, 320, 20, [DishTag::Vegetarian, DishTag::GlutenFree], [Allergen::Milk]],
                    ['Курча табака', 'Pressed chicken', 'Курча під пресом, аджика, печена картопля.', 'Pressed chicken, adjika, roasted potatoes.', 365, 450, 30, [DishTag::Spicy, DishTag::GlutenFree], []],
                ],
            ],
            [
                'name' => ['uk' => 'Десерти', 'en' => 'Desserts'],
                'slug' => 'desserts',
                'unit' => DishUnit::Gram,
                'preorderable' => false,
                'dishes' => [
                    ['Чизкейк Нью-Йорк', 'New York cheesecake', 'Класичний чизкейк, ягідний соус.', 'Classic cheesecake, berry sauce.', 175, 150, 3, [DishTag::Vegetarian], [Allergen::Milk, Allergen::Eggs, Allergen::Gluten], true],
                    ['Тірамісу', 'Tiramisu', 'Маскарпоне, савоярді, еспресо.', 'Mascarpone, savoiardi, espresso.', 180, 150, 3, [DishTag::Vegetarian], [Allergen::Milk, Allergen::Eggs, Allergen::Gluten]],
                    ['Шоколадний фондан', 'Chocolate fondant', 'Теплий фондан, кулька ванільного морозива.', 'Warm fondant, scoop of vanilla ice cream.', 195, 160, 15, [DishTag::Vegetarian], [Allergen::Milk, Allergen::Eggs, Allergen::Gluten]],
                    ['Сирники зі сметаною', 'Syrnyky with sour cream', 'Сирники з домашнього сиру, сметана, варення.', 'Cottage cheese pancakes, sour cream, jam.', 155, 220, 12, [DishTag::Vegetarian], [Allergen::Milk, Allergen::Eggs, Allergen::Gluten]],
                    ['Сорбет', 'Sorbet', 'Три кульки: манго, малина, лимон.', 'Three scoops: mango, raspberry, lemon.', 130, 150, 2, [DishTag::Vegan, DishTag::GlutenFree], []],
                ],
            ],
            [
                'name' => ['uk' => 'Напої', 'en' => 'Drinks'],
                'slug' => 'drinks',
                'unit' => DishUnit::Milliliter,
                'preorderable' => false,
                'dishes' => [
                    ['Еспресо', 'Espresso', 'Арабіка середнього обсмаження.', 'Medium roast arabica.', 55, 30, 2, [DishTag::Vegan, DishTag::GlutenFree], []],
                    ['Капучино', 'Cappuccino', 'Еспресо, молоко, молочна пінка.', 'Espresso, milk, milk foam.', 75, 250, 3, [DishTag::Vegetarian, DishTag::GlutenFree], [Allergen::Milk], true],
                    ['Чай листовий', 'Loose-leaf tea', 'Чорний, зелений або трав\'яний на вибір.', 'Black, green or herbal of your choice.', 85, 500, 3, [DishTag::Vegan, DishTag::GlutenFree], []],
                    ['Лимонад власного виробництва', 'House lemonade', 'Лимон, м\'ята, газована вода.', 'Lemon, mint, sparkling water.', 95, 400, 3, [DishTag::Vegan, DishTag::GlutenFree], []],
                    ['Свіжовичавлений апельсиновий сік', 'Fresh orange juice', 'Апельсини, без цукру.', 'Oranges, no added sugar.', 120, 300, 4, [DishTag::Vegan, DishTag::GlutenFree], []],
                    ['Мінеральна вода', 'Mineral water', 'Газована або негазована.', 'Sparkling or still.', 60, 500, 1, [DishTag::Vegan, DishTag::GlutenFree], []],
                    ['Келих білого вина', 'Glass of white wine', 'Совіньйон блан, Україна.', 'Sauvignon blanc, Ukraine.', 165, 150, 1, [DishTag::Vegan, DishTag::GlutenFree], [Allergen::Sulphites]],
                    ['Келих червоного вина', 'Glass of red wine', 'Сапераві, Україна.', 'Saperavi, Ukraine.', 175, 150, 1, [DishTag::Vegan, DishTag::GlutenFree], [Allergen::Sulphites]],
                ],
            ],
        ];
    }
}
