<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Payments\GatewayManager;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\Gate;
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

        // Платне бронювання й правила попереднього замовлення змінює лише власник (частина 2, п. 3.6)
        Gate::define('manage-booking-payments', fn (User $user) => $user->isOwner());
    }
}
