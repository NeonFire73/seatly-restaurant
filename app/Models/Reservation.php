<?php

namespace App\Models;

use App\Enums\OrderType;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'code', 'token', 'table_id', 'guest_name', 'guest_phone', 'guest_email', 'guests_count',
    'starts_at', 'ends_at', 'status', 'comment', 'locale', 'source', 'expires_at',
    'seated_at', 'cancelled_at', 'cancel_reason', 'created_by',
])]
class Reservation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'source' => ReservationSource::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'expires_at' => 'datetime',
            'seated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasOne<Order, $this>
     */
    public function preorder(): HasOne
    {
        return $this->hasOne(Order::class)->where('type', OrderType::Preorder);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
