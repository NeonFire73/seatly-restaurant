<?php

use Illuminate\Support\Facades\Schedule;

// Фонові задачі (частина 2, розділ 8). Запускаються планувальником: `php artisan schedule:work` або cron
Schedule::command('reservations:mark-no-show')->everyTenMinutes();
Schedule::command('reservations:send-reminders')->everyTenMinutes()->withoutOverlapping();
Schedule::command('reservations:expire-unpaid')->everyMinute()->withoutOverlapping();
Schedule::command('payments:sync')->everyMinute()->withoutOverlapping();
Schedule::command('payments:prune-webhook-logs')->daily();
