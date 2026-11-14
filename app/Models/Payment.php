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
 * @property int $order_id
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property string|null $provider
 * @property string|null $provider_invoice_id
 * @property int $amount
 * @property int $tip_amount
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
    'order_id', 'method', 'provider', 'provider_invoice_id', 'amount', 'tip_amount', 'currency',
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
     * Скільки гість платить насправді: частина рахунку плюс чайові.
     */
    public function total(): int
    {
        return $this->amount + $this->tip_amount;
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
