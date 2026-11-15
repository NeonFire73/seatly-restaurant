<?php

use App\Actions\Payments\HandlePaymentWebhook;
use App\Actions\Payments\StartReservationPayment;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Setting;
use App\Services\Payments\PaymentStatusData;
use Carbon\CarbonImmutable;
use Database\Seeders\WorkingHourSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/**
 * Заклад працює за демо-графіком (пн–чт 11:00–23:00), а «зараз» — понеділок, 16.11.2026, 09:00 за Києвом.
 */
function openRestaurant(): void
{
    test()->seed(WorkingHourSeeder::class);
    test()->travelTo(CarbonImmutable::parse('2026-11-16 09:00', 'Europe/Kyiv'));
}

/**
 * Момент у поясі закладу: kyiv('2026-11-16 18:00').
 */
function kyiv(string $moment): CarbonImmutable
{
    return CarbonImmutable::parse($moment, 'Europe/Kyiv');
}

/**
 * Власник увімкнув платне бронювання (частина 2, п. 3.6); сума — у копійках.
 */
function enableBookingFee(int $kopecks = 20000): void
{
    Setting::put('booking', ['fee_enabled' => true, 'fee_amount' => $kopecks]);
}

/**
 * Те, що зробив би вебхук провайдера після дій гостя на платіжній сторінці.
 */
function settle(Payment $payment, PaymentStatus $status = PaymentStatus::Success): Payment
{
    app(HandlePaymentWebhook::class)->handle(new PaymentStatusData((string) $payment->provider_invoice_id, $status, CarbonImmutable::now()));

    return $payment->refresh();
}

function payReservation(Reservation $reservation): Payment
{
    return settle(app(StartReservationPayment::class)->handle($reservation));
}
