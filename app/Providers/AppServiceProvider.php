<?php

namespace App\Providers;

use App\Services\Payments\GatewayManager;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\ServiceProvider;
use Spatie\Translatable\Facades\Translatable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Шлюз для нових платежів задає PAYMENT_GATEWAY (частина 2, п. 6.1)
        $this->app->bind(PaymentGateway::class, fn () => $this->app->make(GatewayManager::class)->default());
    }

    public function boot(): void
    {
        // Запасні мови розведено: для написів інтерфейсу — APP_FALLBACK_LOCALE (en, бо в українському
        // перекладі Filament бракує частини рядків), а для вмісту з бази — основна мова закладу (uk).
        Translatable::fallback(fallbackLocale: array_key_first(config('seatly.locales')));
    }
}
