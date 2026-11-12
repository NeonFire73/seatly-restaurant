<?php

namespace App\Livewire;

use App\Enums\Allergen;
use App\Enums\DishTag;
use App\Filament\Support\Translations;
use App\Livewire\Concerns\KeepsPageLocale;
use App\Models\Dish;
use App\Models\MenuCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Меню сайту (частина 1, п. 5.2). Режим «кошик» для попереднього замовлення додається на етапі 7.
 */
class MenuBrowser extends Component
{
    use KeepsPageLocale;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** @var list<string> */
    #[Url(except: [])]
    public array $tags = [];

    /** @var list<string> */
    #[Url(except: [])]
    public array $without = [];

    public ?int $openDishId = null;

    public function showDish(int $id): void
    {
        $this->openDishId = $id;
        $this->dispatch('dish-opened');
    }

    public function closeDish(): void
    {
        $this->openDishId = null;
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'tags', 'without');
    }

    /**
     * Значення з адреси сторінки — дані від гостя, тож невідомі позначки й алергени відкидаються.
     */
    public function updated(): void
    {
        $this->tags = array_values(array_intersect($this->tags, array_column(DishTag::cases(), 'value')));
        $this->without = array_values(array_intersect($this->without, array_column(Allergen::cases(), 'value')));
        $this->search = mb_substr(trim($this->search), 0, 60);
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->tags !== [] || $this->without !== [];
    }

    /**
     * @return Collection<int, MenuCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        $this->updated();

        return MenuCategory::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->with(['dishes' => function (HasMany $relation): void {
                $query = $relation->getQuery();
                $query->orderBy('sort');

                if ($this->search !== '') {
                    Translations::search($query, 'name', $this->search);
                }

                foreach ($this->tags as $tag) {
                    $query->whereJsonContains('tags', $tag);
                }

                foreach ($this->without as $allergen) {
                    // Для NULL умова JSON_CONTAINS дає NULL, і страва без алергенів зникла б зі списку
                    $query->where(fn (Builder $q) => $q->whereNull('allergens')->orWhereJsonDoesntContain('allergens', $allergen));
                }
            }])
            ->get()
            ->filter(fn (MenuCategory $category) => $category->dishes->isNotEmpty())
            ->values();
    }

    #[Computed]
    public function openDish(): ?Dish
    {
        if ($this->openDishId === null) {
            return null;
        }

        return Dish::with('category')->find($this->openDishId);
    }

    public function render(): View
    {
        return view('livewire.menu-browser', [
            'tagOptions' => DishTag::cases(),
            'allergenOptions' => Allergen::cases(),
        ]);
    }
}
