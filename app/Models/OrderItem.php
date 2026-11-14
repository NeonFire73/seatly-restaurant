<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use App\Exceptions\Orders\InvalidOrderTransition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * @property OrderItemStatus $status
 */
#[Fillable(['order_id', 'dish_id', 'name', 'price', 'quantity', 'status', 'comment', 'added_by'])]
class OrderItem extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderItemStatus::class,
        ];
    }

    /**
     * Єдине місце зміни статусу позиції (частина 2, п. 4.2).
     */
    public function transitionTo(OrderItemStatus $status): void
    {
        if (! $this->status->canTransitionTo($status)) {
            throw InvalidOrderTransition::ofItem($this->status, $status);
        }

        $this->forceFill(['status' => $status])->save();
    }

    public function total(): int
    {
        return $this->price * $this->quantity;
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Страву могли м'яко видалити з меню, а позиція в старому рахунку має лишитися.
     *
     * @return BelongsTo<Dish, $this>
     */
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
