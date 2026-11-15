<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Mail\ReservationReminderMail;
use App\Models\Reservation;
use App\Services\Booking\BookingSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Фонова задача (частина 2, розділ 8): лист-нагадування за кілька годин до візиту, один раз на бронювання.
 */
#[Signature('reservations:send-reminders')]
#[Description('Надсилає гостям нагадування про бронювання, до яких лишилося кілька годин')]
class SendReservationReminders extends Command
{
    public function handle(): int
    {
        $before = BookingSettings::reminderBefore();

        $reservations = Reservation::query()
            ->where('status', ReservationStatus::Confirmed)
            ->whereNull('reminded_at')
            ->whereNotNull('guest_email')
            ->whereBetween('starts_at', [now(), now()->addMinutes($before)])
            ->get();

        $sent = 0;

        foreach ($reservations as $reservation) {
            // Позначка ставиться умовним оновленням: якщо два запуски перетнулися, лист піде лише від одного
            $claimed = Reservation::whereKey($reservation->id)
                ->whereNull('reminded_at')
                ->update(['reminded_at' => now()]);

            if ($claimed === 0) {
                continue;
            }

            // Бронювання, зроблене вже всередині вікна нагадування, гість і так пам'ятає: лист про
            // підтвердження він отримав щойно. Позначка все одно стоїть, щоб запит більше його не брав
            if ($reservation->created_at->gt($reservation->starts_at->subMinutes($before))) {
                continue;
            }

            Mail::to($reservation->guest_email)
                ->locale($reservation->locale)
                ->queue(new ReservationReminderMail($reservation));

            $sent++;
        }

        $this->info("Надіслано нагадувань: {$sent}");

        return self::SUCCESS;
    }
}
