<?php

namespace App\Actions\Tables;

use App\Enums\WaiterCallStatus;
use App\Exceptions\Orders\WaiterAlreadyCalled;
use App\Models\DiningTable;
use App\Models\WaiterCall;
use App\Notifications\StaffNotifier;
use Illuminate\Support\Facades\DB;

/**
 * Гість кличе офіціанта зі сторінки столика (частина 2, п. 4.5).
 */
class CallWaiter
{
    public function handle(DiningTable $table): WaiterCall
    {
        $call = DB::transaction(function () use ($table): WaiterCall {
            // Блокування столика: два телефони за одним столиком не створять два виклики одночасно
            $locked = DiningTable::whereKey($table->id)->lockForUpdate()->firstOrFail();
            $since = now()->subMinutes((int) config('seatly.orders.waiter_call_interval'));

            if ($locked->waiterCalls()->where('created_at', '>', $since)->exists()) {
                throw WaiterAlreadyCalled::forTable($locked->number);
            }

            return $locked->waiterCalls()->create(['status' => WaiterCallStatus::New]);
        });

        StaffNotifier::waiterCalled($table);

        return $call;
    }
}
