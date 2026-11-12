<?php

use App\Enums\Allergen;
use App\Enums\DishTag;
use App\Livewire\MenuBrowser;
use App\Models\Dish;
use App\Models\MenuCategory;
use Livewire\Livewire;

beforeEach(function () {
    $this->category = MenuCategory::factory()->create(['name' => ['uk' => 'Супи', 'en' => 'Soups']]);
    $this->borscht = Dish::factory()->for($this->category, 'category')->create([
        'name' => ['uk' => 'Борщ', 'en' => 'Borscht'],
        'tags' => [],
        'allergens' => [Allergen::Milk],
    ]);
    $this->soup = Dish::factory()->for($this->category, 'category')->create([
        'name' => ['uk' => 'Гарбузовий суп', 'en' => 'Pumpkin soup'],
        'tags' => [DishTag::Vegan],
        'allergens' => [],
    ]);
});

it('шукає страву будь-якою мовою', function () {
    Livewire::test(MenuBrowser::class)
        ->set('search', 'borsch')
        ->assertSee('Борщ')
        ->assertDontSee('Гарбузовий суп');
});

it('фільтрує за позначкою і виключає алергени', function () {
    Livewire::test(MenuBrowser::class)
        ->set('tags', [DishTag::Vegan->value])
        ->assertSee('Гарбузовий суп')
        ->assertDontSee('Борщ');

    Livewire::test(MenuBrowser::class)
        ->set('without', [Allergen::Milk->value])
        ->assertSee('Гарбузовий суп')
        ->assertDontSee('Борщ');
});

it('відкидає невідомі значення фільтрів з адреси', function () {
    Livewire::withQueryParams(['tags' => ['hacker', DishTag::Vegan->value]])
        ->test(MenuBrowser::class)
        ->assertSet('tags', [DishTag::Vegan->value]);
});

it('показує недоступну страву приглушеною з позначкою', function () {
    $this->borscht->update(['is_available' => false]);

    Livewire::test(MenuBrowser::class)
        ->assertSee('Борщ')
        ->assertSee(__('site.menu.unavailable'));
});

it('не показує вимкнену категорію', function () {
    $this->category->update(['is_active' => false]);

    Livewire::test(MenuBrowser::class)->assertDontSee('Борщ');
});

it('відкриває деталі страви з алергенами', function () {
    Livewire::test(MenuBrowser::class)
        ->call('showDish', $this->borscht->id)
        ->assertSet('openDishId', $this->borscht->id)
        ->assertSee(__('site.menu.allergens'))
        ->assertSee('Молоко');
});

it('зберігає мову сторінки в наступних запитах Livewire', function () {
    app()->setLocale('en');
    $component = Livewire::test(MenuBrowser::class);

    app()->setLocale('uk');

    $component->set('search', 'soup')
        ->assertSee('Pumpkin soup')
        ->assertDontSee('Гарбузовий суп');
});
