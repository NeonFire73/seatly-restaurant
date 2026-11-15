<?php

namespace App\Console\Commands;

use App\Actions\Orders\CancelPreorder;
use App\Actions\Reservations\ExpireReservation;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\Reservation;
use App\Services\Booking\BookingSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Фонова задача (частина 2, розділ 8): бронювання, яке не оплатили за час утримання, звільняє столик.
 * Заодно прибираються попередні замовлення, які гість додав до вже підтвердженого бронювання й не оплатив.
 */
#[Signature('reservations:expire-unpaid')]
#[Description('Звільняє столики неоплачених бронювань і скасовує неоплачені попередні замовлення')]
class ExpireUnpaidReservations extends Command
{
    public function handle(ExpireReservation $expireReservation, CancelPreorder $cancelPreorder): int
    {
        $reservations = Reservation::query()
            ->where('status', ReservationStatus::PendingPayment)
            ->where('expires_at', '<=', now())
            ->get();

        $expired = 0;

        foreach ($reservations as $reservation) {
            if ($expireReservation->handle($reservation)) {
                $expired++;
            }
        }

        $orders = Order::query()
            ->where('type', OrderType::Preorder)
            ->where('status', OrderStatus::AwaitingPayment)
            ->where('created_at', '<=', now()->subMinutes(BookingSettings::paymentHold()))
            ->whereHas('reservation', fn ($query) => $query->where('status', '!=', ReservationStatus::PendingPayment))
            ->get();

        foreach ($orders as $order) {
            $cancelPreorder->handle($order);
        }

        $this->info("Прострочено бронювань: {$expired}, скасовано неоплачених попередніх замовлень: {$orders->count()}");

        return self::SUCCESS;
    }
}
