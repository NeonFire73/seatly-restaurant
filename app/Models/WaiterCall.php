<?php

namespace App\Models;

use App\Enums\WaiterCallStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['table_id', 'status', 'handled_by', 'handled_at'])]
class WaiterCall extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WaiterCallStatus::class,
            'handled_at' => 'datetime',
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
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
