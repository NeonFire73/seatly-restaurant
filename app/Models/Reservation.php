<?php

namespace App\Models;

use App\Enums\OrderType;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exceptions\Booking\InvalidTransition;
use Carbon\CarbonImmutable;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property ReservationStatus $status
 * @property ReservationSource $source
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $seated_at
 * @property CarbonImmutable|null $cancelled_at
 */
#[Fillable([
    'code', 'token', 'table_id', 'guest_name', 'guest_phone', 'guest_email', 'guests_count',
    'starts_at', 'ends_at', 'status', 'comment', 'locale', 'source', 'fee_amount', 'expires_at',
    'seated_at', 'cancelled_at', 'cancel_reason', 'created_by',
])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    // Без схожих символів (0/O, 1/I): код гість може диктувати телефоном
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation): void {
            $reservation->code ??= self::generateCode();
            $reservation->token ??= Str::random(48);
        });
    }

    public static function generateCode(): string
    {
        $code = '';
        $found = false;

        while (! $found) {
            $code = '';

            for ($i = 0; $i < 6; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }

            $found = self::where('code', $code)->doesntExist();
        }

        return $code;
    }

    /**
     * Пошук за токеном із посилання гостя; порівняння — стійке до атак за часом (частина 2, розділ 9).
     */
    public static function findByToken(string $token): ?self
    {
        $reservation = self::where('token', $token)->first();

        if ($reservation === null || ! hash_equals($reservation->token, $token)) {
            return null;
        }

        return $reservation;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'source' => ReservationSource::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'seated_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /**
     * Бронювання, які займають столик.
     *
     * @param  Builder<Reservation>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereIn('status', ReservationStatus::live());
    }

    /**
     * Єдине місце зміни статусу: заборонений перехід кидає виняток (частина 2, п. 3.4).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function transitionTo(ReservationStatus $status, array $attributes = []): void
    {
        if (! $this->status->canTransitionTo($status)) {
            throw InvalidTransition::between($this->status, $status);
        }

        $this->forceFill([...$attributes, 'status' => $status])->save();
    }

    /**
     * Гість може скасувати бронювання сам, доки візит не почався.
     */
    public function isCancellableByGuest(): bool
    {
        return $this->status->canTransitionTo(ReservationStatus::Cancelled) && $this->starts_at->isFuture();
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
