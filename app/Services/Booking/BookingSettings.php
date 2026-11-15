<?php

namespace App\Services\Booking;

use App\Services\SiteContent;

/**
 * Параметри бронювання: типові з config/seatly.php, перекриті значеннями із settings (ключ `booking`).
 */
final class BookingSettings
{
    public static function get(string $key): int
    {
        $stored = (array) SiteContent::setting('booking', []);

        return (int) ($stored[$key] ?? config("seatly.booking.{$key}"));
    }

    public static function duration(): int
    {
        return self::get('duration');
    }

    public static function slotStep(): int
    {
        return max(5, self::get('slot_step'));
    }

    public static function buffer(): int
    {
        return self::get('buffer');
    }

    public static function minLead(): int
    {
        return self::get('min_lead');
    }

    public static function horizonDays(): int
    {
        return self::get('horizon_days');
    }

    public static function lastSlotBeforeClose(): int
    {
        return self::get('last_slot_before_close');
    }

    public static function hold(): int
    {
        return self::get('hold');
    }

    public static function noShowAfter(): int
    {
        return self::get('no_show_after');
    }

    public static function maxGuests(): int
    {
        return self::get('max_guests');
    }

    public static function paymentHold(): int
    {
        return max(1, self::get('payment_hold'));
    }

    public static function preorderMinLead(): int
    {
        return self::get('preorder_min_lead');
    }

    public static function preorderFreeCancel(): int
    {
        return self::get('preorder_free_cancel');
    }

    public static function feeRefundUntil(): int
    {
        return self::get('fee_refund_until');
    }

    /**
     * Плата за нове бронювання із сайту в копійках; 0 — бронювання безкоштовне (частина 2, п. 3.6).
     */
    public static function fee(): int
    {
        if (self::get('fee_enabled') === 0) {
            return 0;
        }

        return max(0, self::get('fee_amount'));
    }
}
