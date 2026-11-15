<?php

namespace App\Livewire\Concerns;

use App\Actions\Orders\AddOrderItem;
use App\Actions\Orders\CreatePreorder;
use App\Models\Dish;
use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

/**
 * Кошик попереднього замовлення (частина 1, п. 5.3) — спільний для майстра бронювання й сторінки бронювання.
 * Сам кошик ($cart: ідентифікатор страви => кількість) оголошує компонент: у майстрі він живе в сесії.
 * З браузера приходять лише ідентифікатори; ціни й суми рахуються тут, на сервері.
 *
 * @property-read Collection<int, MenuCategory> $preorderMenu
 * @property-read list<array{dish: Dish, quantity: int, total: int}> $cartLines
 */
trait HasPreorderCart
{
    public function addDish(int $dishId): void
    {
        $quantity = (int) ($this->cart[$dishId] ?? 0);

        if (! $this->preorderDishes()->has($dishId) || $quantity >= AddOrderItem::MAX_QUANTITY) {
            return;
        }

        if ($quantity === 0 && count($this->cart) >= CreatePreorder::MAX_LINES) {
            return;
        }

        $this->cart[$dishId] = $quantity + 1;
        unset($this->cartLines);
    }

    public function removeDish(int $dishId): void
    {
        $quantity = (int) ($this->cart[$dishId] ?? 0);

        if ($quantity <= 1) {
            unset($this->cart[$dishId]);
        } else {
            $this->cart[$dishId] = $quantity - 1;
        }

        unset($this->cartLines);
    }

    /**
     * Меню для попереднього замовлення: лише страви, які є в наявності й дозволені для нього (частина 2, п. 4.4).
     *
     * @return Collection<int, MenuCategory>
     */
    #[Computed]
    public function preorderMenu(): Collection
    {
        return MenuCategory::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->with(['dishes' => fn ($query) => $query->where('is_available', true)->where('is_preorderable', true)->orderBy('sort')])
            ->get()
            ->filter(fn (MenuCategory $category) => $category->dishes->isNotEmpty())
            ->values();
    }

    /**
     * @return list<array{dish: Dish, quantity: int, total: int}>
     */
    #[Computed]
    public function cartLines(): array
    {
        $dishes = $this->preorderDishes();
        $lines = [];

        foreach ($this->cart as $dishId => $quantity) {
            $dish = $dishes->get((int) $dishId);
            $quantity = min((int) $quantity, AddOrderItem::MAX_QUANTITY);

            // Страву могли зняти з меню, поки кошик лежав у сесії
            if ($dish === null || $quantity < 1) {
                continue;
            }

            $lines[] = ['dish' => $dish, 'quantity' => $quantity, 'total' => $dish->price * $quantity];
        }

        return $lines;
    }

    public function cartTotal(): int
    {
        return array_sum(array_column($this->cartLines, 'total'));
    }

    public function cartCount(): int
    {
        return array_sum(array_column($this->cartLines, 'quantity'));
    }

    /**
     * Кошик без усього, чого вже немає в меню, — у такому вигляді він іде в CreatePreorder.
     *
     * @return array<int, int>
     */
    public function cartItems(): array
    {
        $items = [];

        foreach ($this->cartLines as $line) {
            $items[$line['dish']->id] = $line['quantity'];
        }

        return $items;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Dish>
     */
    private function preorderDishes(): \Illuminate\Support\Collection
    {
        return $this->preorderMenu->flatMap(fn (MenuCategory $category) => $category->dishes)->keyBy('id');
    }
}
