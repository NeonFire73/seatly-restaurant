<?php

namespace App\Filament\Pages;

use App\Actions\Orders\ChangeOrderItemStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\OrderItem;
use BackedEnum;
use Carbon\CarbonInterface;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Сторінка «Кухня» (частина 2, п. 7.3): що готувати зараз і що — до призначеного часу.
 */
class KitchenBoard extends Page
{
    protected string $view = 'filament.pages.kitchen-board';

    protected static ?string $slug = 'kitchen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Кухня';

    public static function canAccess(): bool
    {
        return Gate::allows('cook', Order::class);
    }

    public function startCooking(int $itemId, ChangeOrderItemStatus $changeStatus): void
    {
        $this->change($itemId, OrderItemStatus::Cooking, $changeStatus);
    }

    public function markReady(int $itemId, ChangeOrderItemStatus $changeStatus): void
    {
        $this->change($itemId, OrderItemStatus::Ready, $changeStatus);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $items = OrderItem::query()
            ->whereIn('status', [OrderItemStatus::New, OrderItemStatus::Cooking])
            ->whereHas('order', fn ($query) => $query->open())
            ->with(['order.table', 'dish'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $now = [];
        $scheduled = [];

        foreach ($items->groupBy('order_id') as $orderItems) {
            /** @var OrderItem $first */
            $first = $orderItems->first();
            $startAt = $this->cookingStartsAt($first->order, $orderItems);

            foreach ($orderItems as $item) {
                $card = [
                    'item' => $item,
                    'table' => $first->order->table->number,
                    'minutes' => (int) $item->created_at->diffInMinutes(now()),
                    'startAt' => $startAt,
                ];

                // Попереднє замовлення чекає в колонці «До часу», доки не настане час ставати до плити
                if ($startAt !== null && $startAt->isFuture()) {
                    $scheduled[] = $card;
                } else {
                    $now[] = $card;
                }
            }
        }

        usort($now, fn (array $a, array $b) => [$a['item']->created_at, $a['item']->id] <=> [$b['item']->created_at, $b['item']->id]);
        usort($scheduled, fn (array $a, array $b) => [$a['startAt'], $a['item']->id] <=> [$b['startAt'], $b['item']->id]);

        return ['now' => $now, 'scheduled' => $scheduled];
    }

    /**
     * Коли починати готувати попереднє замовлення: час подачі мінус найдовша страва (частина 2, п. 4.4).
     * Для звичайного замовлення — null: його готують одразу.
     *
     * @param  Collection<int, OrderItem>  $items
     */
    private function cookingStartsAt(Order $order, Collection $items): ?CarbonInterface
    {
        if ($order->type !== OrderType::Preorder || $order->serve_at === null) {
            return null;
        }

        $longest = (int) $items->max(fn (OrderItem $item) => $item->dish->cook_time_minutes ?? 0);

        return $order->serve_at->subMinutes($longest);
    }

    private function change(int $itemId, OrderItemStatus $status, ChangeOrderItemStatus $changeStatus): void
    {
        $item = OrderItem::find($itemId);

        if ($item === null) {
            return;
        }

        try {
            $changeStatus->handle($item, $status);
        } catch (DomainException $e) {
            // Найчастіше — офіціант уже подав чи менеджер скасував позицію, поки екран не оновився
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}
