<?php

namespace App\Livewire;

use App\Actions\Tables\CallWaiter;
use App\Enums\OrderItemStatus;
use App\Exceptions\Orders\WaiterAlreadyCalled;
use App\Livewire\Concerns\KeepsPageLocale;
use App\Models\DiningTable;
use App\Models\MenuCategory;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Сторінка столика (частина 1, п. 5.5): меню для перегляду, рахунок відкритого замовлення й виклик офіціанта.
 * Оплата з чайовими додається на етапі 6. Персональних даних гостя сторінка не показує.
 *
 * @property-read DiningTable|null $table
 * @property-read Order|null $order
 * @property-read Collection<int, MenuCategory> $categories
 */
class TablePage extends Component
{
    use KeepsPageLocale;

    public const TABS = ['menu', 'bill'];

    #[Locked]
    public string $token = '';

    public string $tab = 'menu';

    public function mount(string $token): void
    {
        $this->token = $token;

        abort_if($this->table === null, 404);

        if ($this->order !== null) {
            $this->tab = 'bill';
        }
    }

    /**
     * Токен могли перевипустити, поки сторінка відкрита, — тоді столик більше не знаходиться.
     */
    #[Computed]
    public function table(): ?DiningTable
    {
        return DiningTable::findByQrToken($this->token);
    }

    #[Computed]
    public function order(): ?Order
    {
        $order = $this->table?->openOrder();

        $order?->load(['items' => function (HasMany $relation): void {
            $relation->getQuery()->where('status', '!=', OrderItemStatus::Cancelled)->orderBy('id');
        }]);

        return $order;
    }

    /**
     * @return Collection<int, MenuCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return MenuCategory::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->with(['dishes' => function (HasMany $relation): void {
                $relation->getQuery()->orderBy('sort');
            }])
            ->get()
            ->filter(fn (MenuCategory $category) => $category->dishes->isNotEmpty())
            ->values();
    }

    public function showTab(string $tab): void
    {
        if (in_array($tab, self::TABS, true)) {
            $this->tab = $tab;
        }
    }

    /**
     * Властивість tab може змінити й сам браузер, тож невідоме значення замінюється на меню.
     */
    public function updatedTab(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'menu';
        }
    }

    public function callWaiter(CallWaiter $callWaiter): void
    {
        $table = $this->table;

        if ($table === null) {
            return;
        }

        try {
            $callWaiter->handle($table);
            $this->dispatch('toast', message: __('table.call.sent'));
        } catch (WaiterAlreadyCalled) {
            $this->dispatch('toast', message: __('table.call.already'));
        }
    }

    public function render(): View
    {
        return view('livewire.table-page');
    }
}
