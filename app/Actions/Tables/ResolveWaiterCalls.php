<?php

namespace App\Actions\Tables;

use App\Enums\WaiterCallStatus;
use App\Models\DiningTable;
use App\Models\User;

/**
 * Офіціант підійшов до столика: усі його нові виклики позначаються виконаними (частина 2, п. 7.2).
 */
class ResolveWaiterCalls
{
    public function handle(DiningTable $table, User $by): int
    {
        return $table->waiterCalls()
            ->where('status', WaiterCallStatus::New)
            ->update([
                'status' => WaiterCallStatus::Done,
                'handled_by' => $by->id,
                'handled_at' => now(),
            ]);
    }
}
