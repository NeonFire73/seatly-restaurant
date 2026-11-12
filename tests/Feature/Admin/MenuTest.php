<?php

use App\Enums\Allergen;
use App\Enums\DishTag;
use App\Filament\Resources\Dishes\Pages\CreateDish;
use App\Filament\Resources\Dishes\Pages\EditDish;
use App\Filament\Resources\Dishes\Pages\ListDishes;
use App\Models\Dish;
use App\Models\MenuCategory;
use App\Models\User;

use function Pest\Livewire\livewire;

it('створює страву: ціна в гривнях зберігається в копійках, назва двома мовами', function () {
    $this->actingAs(User::factory()->manager()->create());
    $category = MenuCategory::factory()->create();

    livewire(CreateDish::class)
        ->fillForm([
            'name' => ['uk' => 'Борщ', 'en' => 'Borscht'],
            'description' => ['uk' => 'Зі сметаною', 'en' => null],
            'menu_category_id' => $category->id,
            'price' => '185,50',
            'weight' => 400,
            'cook_time_minutes' => 10,
            'tags' => [DishTag::Spicy->value],
            'allergens' => [Allergen::Milk->value, Allergen::Celery->value],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $dish = Dish::sole();

    expect($dish->price)->toBe(18550)
        ->and($dish->getTranslation('name', 'en'))->toBe('Borscht')
        ->and($dish->tags->all())->toBe([DishTag::Spicy])
        ->and($dish->allergens)->toHaveCount(2)
        // Англійського опису немає — показується український
        ->and($dish->getTranslation('description', 'en'))->toBe('Зі сметаною');
});

it('показує збережену ціну у формі в гривнях', function () {
    $this->actingAs(User::factory()->owner()->create());
    $dish = Dish::factory()->create(['price' => 12050]);

    livewire(EditDish::class, ['record' => $dish->getRouteKey()])
        ->assertSchemaStateSet(['price' => '120.50']);
});

it('не приймає ціну з трьома знаками після коми', function () {
    $this->actingAs(User::factory()->manager()->create());

    livewire(CreateDish::class)
        ->fillForm([
            'name' => ['uk' => 'Чай'],
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'price' => '10.999',
        ])
        ->call('create')
        ->assertHasFormErrors(['price' => 'regex']);
});

it('дозволяє офіціанту перемкнути наявність страви, але не редагувати її', function () {
    $this->actingAs($waiter = User::factory()->create());
    $dish = Dish::factory()->create();

    livewire(ListDishes::class)
        ->call('updateTableColumnState', 'is_available', (string) $dish->getKey(), false);

    expect($dish->fresh()->is_available)->toBeFalse();

    $this->get("/admin/dishes/{$dish->getKey()}/edit")->assertForbidden();
});

it('не дає видалити категорію, у якій є страви', function () {
    $owner = User::factory()->owner()->create();
    $category = MenuCategory::factory()->create();
    $dish = Dish::factory()->for($category, 'category')->create();
    $dish->delete();

    expect($owner->can('delete', $category))->toBeFalse();

    $dish->forceDelete();

    expect($owner->can('delete', $category->fresh()))->toBeTrue();
});

it('шукає страви будь-якою мовою', function () {
    $this->actingAs(User::factory()->manager()->create());
    $borscht = Dish::factory()->create(['name' => ['uk' => 'Борщ', 'en' => 'Borscht']]);
    $tea = Dish::factory()->create(['name' => ['uk' => 'Чай', 'en' => 'Tea']]);

    livewire(ListDishes::class)
        ->searchTable('borsch')
        ->assertCanSeeTableRecords([$borscht])
        ->assertCanNotSeeTableRecords([$tea]);
});
