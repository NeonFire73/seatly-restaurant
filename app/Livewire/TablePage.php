<?php

namespace App\Livewire;

use App\Actions\Payments\StartPayment;
use App\Actions\Tables\CallWaiter;
use App\Enums\OrderItemStatus;
use App\Exceptions\Orders\WaiterAlreadyCalled;
use App\Exceptions\Payments\BillChanged;
use App\Exceptions\Payments\PaymentException;
use App\Livewire\Concerns\KeepsPageLocale;
use App\Models\DiningTable;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Services\Payments\TipSettings;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Сторінка столика (частина 1, пп. 5.5 і 5.6): меню для перегляду, рахунок відкритого замовлення,
 * виклик офіціанта й оплата з чайовими. Персональних даних гостя сторінка не показує.
 *
 * @property-read DiningTable|null $table
 * @property-read Order|null $order
 * @property-read Collection<int, MenuCategory> $categories
 * @property-read int|null $tipAmount
 */
class TablePage extends Component
{
    use KeepsPageLocale;

    public const TABS = ['menu', 'bill'];

    private const PAY_ATTEMPTS_PER_MINUTE = 6;

    #[Locked]
    public string $token = '';

    public string $tab = 'menu';

    public bool $paying = false;

    public int $tipPercent = 0;

    // Гість вводить власну суму чайових замість відсотка
    public bool $customTipMode = false;

    public string $customTip = '';

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

        $order?->load(['waiter', 'items' => function (HasMany $relation): void {
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

    /**
     * Чайові в копійках, пораховані на сервері від суми до сплати (частина 2, розділи 5 і 9).
     * null — власну суму введено з помилкою або вона завелика.
     */
    #[Computed]
    public function tipAmount(): ?int
    {
        $due = $this->order?->amountDue() ?? 0;

        if (! $this->customTipMode) {
            return TipSettings::amountFor($due, $this->tipPercent);
        }

        if (trim($this->customTip) === '') {
            return 0;
        }

        try {
            $amount = Money::toKopecks($this->customTip);
        } catch (InvalidArgumentException) {
            return null;
        }

        if ($amount > TipSettings::maxFor($due)) {
            return null;
        }

        return $amount;
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

    public function openPay(): void
    {
        if ($this->order === null || $this->order->amountDue() === 0) {
            return;
        }

        $this->paying = true;
        $this->tipPercent = TipSettings::defaultPercent();
        $this->customTipMode = false;
        $this->customTip = '';
    }

    public function closePay(): void
    {
        $this->paying = false;
    }

    public function selectTip(int $percent): void
    {
        if (in_array($percent, TipSettings::percents(), true)) {
            $this->tipPercent = $percent;
            $this->customTipMode = false;
            $this->customTip = '';
        }
    }

    public function useCustomTip(): void
    {
        $this->customTipMode = true;
    }

    /**
     * Відсоток міг підмінити браузер: дозволені лише варіанти з налаштувань.
     */
    public function updatedTipPercent(): void
    {
        if (! in_array($this->tipPercent, TipSettings::percents(), true)) {
            $this->tipPercent = TipSettings::defaultPercent();
        }
    }

    public function updatedCustomTip(): void
    {
        $this->resetErrorBag('customTip');
    }

    public function pay(StartPayment $startPayment): void
    {
        $order = $this->order;
        $tip = $this->tipAmount;

        if ($order === null || ! $this->paying) {
            return;
        }

        $this->resetErrorBag('customTip');

        if ($tip === null) {
            $this->addError('customTip', __('payment.errors.tip_invalid', ['max' => Money::format(TipSettings::maxFor($order->amountDue()))]));

            return;
        }

        $key = 'pay:'.$this->token.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::PAY_ATTEMPTS_PER_MINUTE)) {
            $this->dispatch('toast', message: __('payment.errors.too_many'));

            return;
        }

        RateLimiter::hit($key, 60);

        try {
            $payment = $startPayment->handle($order, $tip, $order->amountDue(), $this->locale);
        } catch (BillChanged $e) {
            // Панель лишається відкритою вже з новою сумою: гість має побачити її, перш ніж платити
            unset($this->order, $this->tipAmount);
            $this->paying = $this->order !== null && $this->order->amountDue() > 0;
            $this->dispatch('toast', message: $e->getMessage());

            return;
        } catch (PaymentException $e) {
            $this->dispatch('toast', message: $e->getMessage());

            return;
        }

        $this->redirect((string) $payment->page_url);
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
        // Рахунок могли сплатити чи закрити, поки панель оплати відкрита
        if ($this->paying && ($this->order === null || $this->order->amountDue() === 0)) {
            $this->paying = false;
        }

        return view('livewire.table-page');
    }
}
