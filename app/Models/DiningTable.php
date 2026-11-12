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
