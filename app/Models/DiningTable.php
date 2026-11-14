<?php

namespace App\Models;

use App\Enums\TableShape;
use Database\Factories\DiningTableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Столик. Клас названо DiningTable, бо назва Table конфліктує з таблицями Filament.
 */
#[Table('tables')]
#[Fillable(['hall_id', 'number', 'seats', 'min_guests', 'shape', 'x', 'y', 'width', 'height', 'rotation', 'is_active'])]
class DiningTable extends Model
{
    /** @use HasFactory<DiningTableFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (DiningTable $table): void {
            $table->qr_token ??= self::generateQrToken();
        });
    }

    public static function generateQrToken(): string
    {
        return Str::random(40);
    }

    /**
     * Старий QR-код після цього перестає працювати.
     */
    public function regenerateQrToken(): void
    {
        $this->qr_token = self::generateQrToken();
        $this->save();
    }

    /**
     * Пошук за токеном із QR-коду; порівняння — стійке до атак за часом (частина 2, розділ 9).
     * Вимкнений столик гостям не відкривається.
     */
    public static function findByQrToken(string $token): ?self
    {
        $table = self::where('qr_token', $token)->where('is_active', true)->first();

        if ($table === null || ! hash_equals($table->qr_token, $token)) {
            return null;
        }

        return $table;
    }

    /**
     * Адреса, зашита у QR-код. Мовного префікса немає: сторінка сама відкриється мовою браузера гостя.
     */
    public function qrUrl(): string
    {
        return route('table.show', ['token' => $this->qr_token]);
    }

    /**
     * На столику одночасно може бути лише одне відкрите замовлення (частина 2, п. 4.1).
     */
    public function openOrder(): ?Order
    {
        return $this->orders()->open()->latest('id')->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shape' => TableShape::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Hall, $this>
     */
    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'table_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    /**
     * @return HasMany<WaiterCall, $this>
     */
    public function waiterCalls(): HasMany
    {
        return $this->hasMany(WaiterCall::class, 'table_id');
    }
}
