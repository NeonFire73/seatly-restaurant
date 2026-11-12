<?php

namespace App\Support;

use App\Models\Reservation;
use App\Services\SiteContent;

/**
 * Подія календаря (.ics) для бронювання: вкладення в лист і кнопка «Додати в календар».
 */
final class Ics
{
    private const UTC_FORMAT = 'Ymd\THis\Z';

    public static function forReservation(Reservation $reservation): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Seatly Restaurant//Booking//UK',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:reservation-'.$reservation->code.'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.now()->utc()->format(self::UTC_FORMAT),
            'DTSTART:'.$reservation->starts_at->utc()->format(self::UTC_FORMAT),
            'DTEND:'.$reservation->ends_at->utc()->format(self::UTC_FORMAT),
            'SUMMARY:'.self::escape(__('booking.ics.summary', ['brand' => config('app.name')], $reservation->locale)),
            'DESCRIPTION:'.self::escape(__('booking.ics.description', [
                'code' => $reservation->code,
                'table' => $reservation->table->number,
                'guests' => $reservation->guests_count,
            ], $reservation->locale)),
            'URL:'.Localization::route('reservation.show', ['token' => $reservation->token], $reservation->locale),
        ];

        $address = SiteContent::contacts()['address'];
        $location = $address[$reservation->locale] ?? $address[Localization::main()] ?? null;

        if ($location) {
            $lines[] = 'LOCATION:'.self::escape($location);
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        // RFC 5545 вимагає CRLF
        return implode("\r\n", $lines)."\r\n";
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
    }
}
