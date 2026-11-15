<?php

namespace App\Filament\Pages;

use App\Actions\Orders\AddOrderItem;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CancelOrderItem;
use App\Actions\Orders\ChangeOrderItemStatus;
use App\Actions\Orders\CloseOrder;
use App\Actions\Orders\OpenOrder;
use App\Actions\Orders\RecordOfflinePayment;
use App\Actions\Orders\RemoveOrderItem;
use App\Actions\Reservations\SeatReservation;
use App\Actions\Tables\ResolveWaiterCalls;
use App\Enums\OrderItemStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\WaiterCallStatus;
use App\Filament\Support\Translations;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Hall;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WaiterCall;
use App\Support\LocalTime;
use BackedEnum;
use Closure;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;

/**
 * Сторінка «Зал» — робоче місце офіціанта (частина 2, п. 7.2): столики з кольоровими станами
 * й панель обраного столика. Самі зміни виконують класи з app/Actions; тут лише кнопки й права.
 * Клас названо HallBoard, бо назву Hall має модель залу.
 */
class HallBoard extends Page
{
    // Стан столика; порядок — від найважливішого: він визначає, що побачить офіціант, коли збіглося кілька
    public const STATES = [
        'call' => 'Кличе офіціанта',
        'due' => 'Є сума до сплати',
        'paid' => 'Сплачено повністю',
        'busy' => 'Зайнятий',
        'soon' => 'Скоро бронювання',
        'free' => 'Вільний',
    ];

    // За скільки хвилин до бронювання вільний столик позначається «скоро бронювання»
    private const SOON_MINUTES = 30;

    private const DISH_LIMIT = 40;

    protected string $view = 'filament.pages.hall-board';

    protected static ?string $slug = 'hall';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Зал';

    #[Url]
    public ?int $hall = null;

    #[Url(as: 'table')]
    public ?int $tableId = null;

    public string $search = '';

    public ?int $categoryId = null;

    public int $quantity = 1;

    public string $comment = '';

    public static function canAccess(): bool
    {
        return Gate::allows('serve', Order::class);
    }

    public function selectHall(int $hallId): void
    {
        $this->hall = $hallId;
        $this->tableId = null;
    }

    public function selectTable(?int $tableId): void
    {
        $this->tableId = $tableId;
        $this->reset('search', 'categoryId', 'quantity', 'comment');
    }

    public function openOrder(OpenOrder $openOrder): void
    {
        $table = $this->selectedTable();

        if ($table === null) {
            return;
        }

        $this->run(
            fn () => $openOrder->handle($table, $this->waiterForNewOrder()),
            "Замовлення на столику №{$table->number} відкрито",
        );
    }

    public function seat(int $reservationId, SeatReservation $seatReservation): void
    {
        $table = $this->selectedTable();
        $reservation = $table?->reservations()->whereKey($reservationId)->first();

        if ($reservation === null || Gate::denies('seat', $reservation)) {
            return;
        }

        $this->run(
            fn () => $seatReservation->handle($reservation, $this->user()),
            'Гостей посаджено, замовлення відкрито',
        );
    }

    public function addDish(int $dishId, AddOrderItem $addItem): void
    {
        $order = $this->selectedTable()?->openOrder();
        $dish = Dish::find($dishId);

        if ($order === null || $dish === null) {
            return;
        }

        $this->run(
            fn () => $addItem->handle($order, $dish, $this->quantity, $this->comment, $this->user()),
            "Додано: {$dish->name}",
        );

        $this->reset('quantity', 'comment');
    }

    public function markServed(int $itemId, ChangeOrderItemStatus $changeStatus): void
    {
        $item = $this->orderItem($itemId);

        if ($item === null) {
            return;
        }

        $this->run(fn () => $changeStatus->handle($item, OrderItemStatus::Served), 'Позначено подане');
    }

    public function removeItem(int $itemId, RemoveOrderItem $removeItem): void
    {
        $item = $this->orderItem($itemId);

        if ($item === null) {
            return;
        }

        $this->run(fn () => $removeItem->handle($item), 'Позицію прибрано');
    }

    public function cancelItem(int $itemId, CancelOrderItem $cancelItem): void
    {
        $item = $this->orderItem($itemId);

        if ($item === null || Gate::denies('cancel', Order::class)) {
            return;
        }

        $this->run(fn () => $cancelItem->handle($item, $this->user()), 'Позицію скасовано');
    }

    public function acceptPayment(string $method, RecordOfflinePayment $recordPayment): void
    {
        $order = $this->selectedTable()?->openOrder();
        $method = PaymentMethod::tryFrom($method);

        if ($order === null || $method === null) {
            return;
        }

        $this->run(
            fn () => $recordPayment->handle($order, $method, $this->user()),
            'Оплату прийнято: '.mb_strtolower($method->getLabel()),
        );
    }

    public function closeOrder(CloseOrder $closeOrder): void
    {
        $table = $this->selectedTable();
        $order = $table?->openOrder();

        if ($table === null || $order === null) {
            return;
        }

        $this->run(fn () => $closeOrder->handle($order), "Замовлення закрито, столик №{$table->number} вільний");
    }

    public function cancelOrder(CancelOrder $cancelOrder): void
    {
        $order = $this->selectedTable()?->openOrder();

        if ($order === null || Gate::denies('cancel', Order::class)) {
            return;
        }

        $this->run(fn () => $cancelOrder->handle($order), 'Замовлення скасовано');
    }

    public function resolveCall(ResolveWaiterCalls $resolveCalls): void
    {
        $table = $this->selectedTable();

        if ($table === null) {
            return;
        }

        $resolveCalls->handle($table, $this->user());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $halls = Hall::where('is_active', true)->orderBy('sort')->get();
        $current = $halls->firstWhere('id', $this->hall) ?? $halls->first();

        if ($current === null) {
            return ['halls' => $halls, 'current' => null, 'cards' => [], 'panel' => null, 'canCancel' => false];
        }

        $tables = DiningTable::where('hall_id', $current->id)
            ->where('is_active', true)
            ->get()
            ->sortBy('number', SORT_NATURAL)
            ->values();
        $ids = $tables->modelKeys();
        $now = now();

        $orders = Order::atTable()->whereIn('table_id', $ids)->with('items')->get()->keyBy('table_id');
        $calls = WaiterCall::where('status', WaiterCallStatus::New)->whereIn('table_id', $ids)->pluck('table_id')->flip();
        $reservations = Reservation::whereIn('table_id', $ids)
            ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::Seated])
            ->where('starts_at', '<', LocalTime::now()->endOfDay()->utc())
            ->where('ends_at', '>', $now)
            ->orderBy('starts_at')
            ->get()
            ->groupBy('table_id');

        $cards = [];
        $panel = null;

        foreach ($tables as $table) {
            $order = $orders->get($table->id);
            /** @var Collection<int, Reservation> $tableReservations */
            $tableReservations = $reservations->get($table->id, new Collection);
            $state = $this->stateOf($order, $calls->has($table->id), $tableReservations);

            $cards[] = [
                'table' => $table,
                'state' => $state,
                'due' => $order?->amountDue() ?? 0,
                'next' => $tableReservations->firstWhere('status', ReservationStatus::Confirmed),
            ];

            if ($table->id === $this->tableId) {
                $panel = [
                    'table' => $table,
                    'state' => $state,
                    'order' => $order,
                    'hasCall' => $calls->has($table->id),
                    'reservations' => $tableReservations,
                ];
            }
        }

        if ($panel !== null && $panel['order'] !== null) {
            $panel['dishes'] = $this->dishes();
            $panel['categories'] = MenuCategory::where('is_active', true)->orderBy('sort')->get();
        }

        return [
            'halls' => $halls,
            'current' => $current,
            'cards' => $cards,
            'panel' => $panel,
            'canCancel' => Gate::allows('cancel', Order::class),
        ];
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     */
    private function stateOf(?Order $order, bool $hasCall, Collection $reservations): string
    {
        if ($hasCall) {
            return 'call';
        }

        if ($order !== null) {
            if ($order->amountDue() > 0) {
                return 'due';
            }

            if ($order->subtotal > 0) {
                return 'paid';
            }

            return 'busy';
        }

        if ($reservations->contains('status', ReservationStatus::Seated)) {
            return 'busy';
        }

        $soon = now()->addMinutes(self::SOON_MINUTES);

        if ($reservations->contains(fn (Reservation $reservation) => $reservation->starts_at->lte($soon))) {
            return 'soon';
        }

        return 'free';
    }

    /**
     * @return Collection<int, Dish>
     */
    private function dishes(): Collection
    {
        $search = mb_substr(trim($this->search), 0, 60);
        $query = Dish::query()->where('is_available', true)->orderBy('menu_category_id')->orderBy('sort');

        if ($search !== '') {
            Translations::search($query, 'name', $search);
        }

        if ($this->categoryId !== null) {
            $query->where('menu_category_id', $this->categoryId);
        }

        return $query->limit(self::DISH_LIMIT)->get();
    }

    /**
     * Ідентифікатори приходять із браузера, тож столик шукається лише серед активних,
     * а позиція — лише у відкритому замовленні обраного столика.
     */
    private function selectedTable(): ?DiningTable
    {
        if ($this->tableId === null) {
            return null;
        }

        return DiningTable::where('is_active', true)->find($this->tableId);
    }

    private function orderItem(int $itemId): ?OrderItem
    {
        return $this->selectedTable()?->openOrder()?->items()->whereKey($itemId)->first();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    /**
     * Замовлення закріплюється за офіціантом, який його відкрив; за менеджером — ні.
     */
    private function waiterForNewOrder(): ?User
    {
        $user = $this->user();

        if ($user->isManagement()) {
            return null;
        }

        return $user;
    }

    /**
     * Доменна помилка (столик зайнятий, заборонений перехід) показується сповіщенням, а не сторінкою 500.
     */
    private function run(Closure $callback, string $success): void
    {
        try {
            $callback();
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($success)->success()->send();
    }
}
