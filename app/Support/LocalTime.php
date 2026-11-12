<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Час закладу. У базі все в UTC, а години роботи, слоти й дати в інтерфейсі — у поясі закладу.
 */
final class LocalTime
{
    public static function zone(): string
    {
        return config('seatly.timezone');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zone());
    }

    public static function of(CarbonInterface $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)->setTimezone(self::zone());
    }

    /**
     * Місцеві дата й час → момент (у поясі закладу). Некоректний рядок дає null.
     */
    public static function parse(string $date, string $time = '00:00'): ?CarbonImmutable
    {
        // У суворому режимі Carbon на некоректний рядок кидає виняток, а не повертає false
        try {
            $moment = CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$date} {$time}", self::zone());
        } catch (InvalidFormatException) {
            return null;
        }

        // Carbon «перекручує» 2026-02-31 у березень, тож зворотне форматування має збігтися
        if ($moment === null || $moment->format('Y-m-d H:i') !== "{$date} {$time}") {
            return null;
        }

        return $moment;
    }

    /**
     * «12 листопада, четвер» мовою сторінки.
     */
    public static function dateLabel(CarbonInterface $moment, ?string $locale = null): string
    {
        return self::of($moment)->locale($locale ?? app()->getLocale())->isoFormat('D MMMM, dddd');
    }

    public static function timeLabel(CarbonInterface $moment): string
    {
        return self::of($moment)->format('H:i');
    }
}
