<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\User;
use App\Support\LocalTime;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

/**
 * Сповіщення персоналу в адмін-панелі (дзвіночок) про події з бронюваннями (частина 2, п. 3.7).
 */
final class StaffNotifier
{
    public static function reservationCreated(Reservation $reservation): void
    {
        self::send(
            Notification::make()
                ->title('Нове бронювання '.$reservation->code)
                ->body(self::summary($reservation))
                ->icon('heroicon-o-calendar-days')
                ->success(),
            $reservation->created_by,
        );
    }

    public static function reservationCancelled(Reservation $reservation, ?User $by): void
    {
        $who = 'гостем';

        if ($by !== null) {
            $who = 'працівником '.$by->name;
        }

        self::send(
            Notification::make()
                ->title('Бронювання '.$reservation->code.' скасовано '.$who)
                ->body(self::summary($reservation))
                ->icon('heroicon-o-x-circle')
                ->warning(),
            $by?->id,
        );
    }

    /**
     * Власникам і менеджерам, крім того, хто сам виконав дію.
     */
    private static function send(Notification $notification, ?int $exceptUserId): void
    {
        $users = User::query()
            ->whereIn('role', [UserRole::Owner, UserRole::Manager])
            ->where('is_active', true)
            ->when($exceptUserId !== null, fn (Builder $query) => $query->whereKeyNot($exceptUserId))
            ->get();

        $notification->sendToDatabase($users);
    }

    private static function summary(Reservation $reservation): string
    {
        return sprintf(
            '%s о %s, столик №%s, гостей: %d, %s',
            LocalTime::of($reservation->starts_at)->format('d.m'),
            LocalTime::timeLabel($reservation->starts_at),
            $reservation->table->number,
            $reservation->guests_count,
            $reservation->guest_name,
        );
    }
}
