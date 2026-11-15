<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int|null $order_id
 * @property int|null $reservation_id
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property string|null $provider
 * @property string|null $provider_invoice_id
 * @property int $amount
 * @property int $tip_amount
 * @property int $booking_fee_amount
 * @property int $refunded_amount
 * @property int|null $waiter_id
 * @property string|null $page_url
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $meta
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $paid_at
 * @property CarbonInterface|null $refunded_at
 * @property CarbonInterface|null $provider_modified_at
 * @property CarbonInterface|null $created_at
 */
#[Fillable([
    'order_id', 'reservation_id', 'method', 'provider', 'provider_invoice_id', 'amount', 'tip_amount', 'booking_fee_amount', 'currency',
    'status', 'waiter_id', 'page_url', 'expires_at', 'paid_at', 'refunded_at', 'refunded_amount',
    'provider_modified_at', 'failure_reason', 'meta', 'recorded_by',
])]
class Payment extends Model
{
    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'provider_modified_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * Неоплачені онлайн-рахунки, за якими гість ще може заплатити.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopePendingOnline(Builder $query): void
    {
        $query->where('method', PaymentMethod::Online)
            ->whereIn('status', [PaymentStatus::Created, PaymentStatus::Processing]);
    }

    /**
     * Скільки гість платить насправді: частина рахунку, чайові й плата за бронювання.
     */
    public function total(): int
    {
        return $this->amount + $this->tip_amount + $this->booking_fee_amount;
    }

    /**
     * Скільки ще можна повернути.
     */
    public function refundable(): int
    {
        if ($this->status !== PaymentStatus::Success) {
            return 0;
        }

        return max(0, $this->total() - $this->refunded_amount);
    }

    /**
     * Часткове повернення (частина 2, п. 3.6): плата за бронювання й сума страв повертаються кожна
     * за своїм правилом, тож платіж пам'ятає, скільки повернено з кожної частини.
     */
    public function refundedFee(): int
    {
        if ($this->status === PaymentStatus::Refunded) {
            return $this->booking_fee_amount;
        }

        return min($this->booking_fee_amount, (int) ($this->meta['refund_parts']['fee'] ?? 0));
    }

    public function refundedOrderAmount(): int
    {
        if ($this->status === PaymentStatus::Refunded) {
            return $this->amount;
        }

        return min($this->amount, (int) ($this->meta['refund_parts']['order'] ?? 0));
    }

    public function rememberRefundedParts(int $fee, int $order): void
    {
        $meta = $this->meta ?? [];
        $meta['refund_parts'] = [
            'fee' => $this->refundedFee() + $fee,
            'order' => $this->refundedOrderAmount() + $order,
        ];
        $this->meta = $meta;
    }

    public function isOnline(): bool
    {
        return $this->method === PaymentMethod::Online;
    }

    /**
     * Мова, якою гість починав оплату: нею відкривається сторінка результату.
     */
    public function locale(): ?string
    {
        return $this->meta['locale'] ?? null;
    }

    /**
     * Історія статусів для картки платежу в адмінці (частина 2, п. 7.7).
     *
     * @return list<array{status: string, at: string}>
     */
    public function history(): array
    {
        return array_values($this->meta['history'] ?? []);
    }

    public function rememberStatus(PaymentStatus $status, CarbonInterface $at): void
    {
        $meta = $this->meta ?? [];
        $meta['history'][] = ['status' => $status->value, 'at' => $at->toIso8601String()];
        $this->meta = $meta;
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * Отримувач чайових.
     *
     * @return BelongsTo<User, $this>
     */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return HasMany<PaymentWebhookLog, $this>
     */
    public function webhookLogs(): HasMany
    {
        return $this->hasMany(PaymentWebhookLog::class, 'provider_invoice_id', 'provider_invoice_id');
    }
}
