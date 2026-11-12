<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
