<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Translatable\Facades\Translatable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Запасні мови розведено: для написів інтерфейсу — APP_FALLBACK_LOCALE (en, бо в українському
        // перекладі Filament бракує частини рядків), а для вмісту з бази — основна мова закладу (uk).
        Translatable::fallback(fallbackLocale: array_key_first(config('seatly.locales')));
    }
}
