<?php

use Illuminate\Support\Facades\Schedule;

// Фонові задачі (частина 2, розділ 8). Запускаються планувальником: `php artisan schedule:work` або cron
Schedule::command('reservations:mark-no-show')->everyTenMinutes();
