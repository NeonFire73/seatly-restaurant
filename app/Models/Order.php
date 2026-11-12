<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number', 'table_id', 'reservation_id', 'waiter_id', 'type', 'status', 'serve_at',
    'subtotal', 'paid_total', 'comment', 'opened_at', 'closed_at',
])]
class Order extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'serve_at' => 'datetime',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function amountDue(): int
    {
        return max(0, $this->subtotal - $this->paid_total);
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
