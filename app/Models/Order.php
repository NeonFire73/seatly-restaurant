<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Exceptions\Orders\InvalidOrderTransition;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property OrderStatus $status
 * @property OrderType $type
 * @property CarbonImmutable|null $serve_at
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $closed_at
 */
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

    protected static function booted(): void
    {
        // Номер — ідентифікатор із нулями попереду («000042»); до вставки рядка він невідомий,
        // тож спершу записується тимчасове унікальне значення
        static::creating(function (Order $order): void {
            $order->number ??= 'tmp-'.Str::random(12);
        });

        static::created(function (Order $order): void {
            if (str_starts_with($order->number, 'tmp-')) {
                $order->number = sprintf('%06d', $order->id);
                $order->saveQuietly();
            }
        });
    }

    /**
     * @param  Builder<Order>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', OrderStatus::Open);
    }

    /**
     * Замовлення, які зараз обслуговуються за столиками. Оплачене попереднє замовлення теж відкрите,
     * але столик не займає, доки гостей не посадили (частина 2, п. 4.4).
     *
     * @param  Builder<Order>  $query
     */
    public function scopeAtTable(Builder $query): void
    {
        $query->where('status', OrderStatus::Open)->where('type', OrderType::DineIn);
    }

    /**
     * Єдине місце зміни статусу: заборонений перехід кидає виняток (частина 2, п. 4.1).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function transitionTo(OrderStatus $status, array $attributes = []): void
    {
        if (! $this->status->canTransitionTo($status)) {
            throw InvalidOrderTransition::ofOrder($this->status, $status);
        }

        $this->forceFill([...$attributes, 'status' => $status])->save();
    }

    /**
     * Сума рахується заново з позицій, а не додаванням: повторний виклик нічого не псує.
     * Скасовані позиції в суму не входять (частина 2, п. 2.3).
     */
    public function recalculateSubtotal(): void
    {
        $subtotal = (int) $this->items()
            ->where('status', '!=', OrderItemStatus::Cancelled)
            ->sum(DB::raw('price * quantity'));

        $this->forceFill(['subtotal' => $subtotal])->save();
    }

    /**
     * Сплачене — сума успішних платежів, а не лічильник: повторна обробка того самого платежу
     * нічого не подвоює (частина 2, п. 6.3). Чайові сюди не входять.
     */
    public function recalculatePaidTotal(): void
    {
        $paid = 0;

        // Не SQL-сумою: із частково поверненого платежу в сплаченому лишається тільки неповернена частина
        foreach ($this->payments()->where('status', PaymentStatus::Success)->get() as $payment) {
            $paid += $payment->amount - $payment->refundedOrderAmount();
        }

        $this->forceFill(['paid_total' => $paid])->save();
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
