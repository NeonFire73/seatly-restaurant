<?php

namespace App\Services\Payments;

use App\Services\SiteContent;

/**
 * Параметри чайових (частина 2, розділ 5): типові з config/seatly.php, перекриті значеннями із settings (ключ `tips`).
 */
final class TipSettings
{
    /**
     * @return list<int>
     */
    public static function percents(): array
    {
        $percents = array_map(intval(...), (array) self::get('percents'));

        return array_values(array_unique(array_filter($percents, fn (int $percent) => $percent >= 0 && $percent <= 100)));
    }

    public static function defaultPercent(): int
    {
        $default = (int) self::get('default_percent');

        if (in_array($default, self::percents(), true)) {
            return $default;
        }

        return 0;
    }

    public static function showWaiterName(): bool
    {
        return (bool) self::get('show_waiter_name');
    }

    /**
     * Чайові рахуються від суми, яку гість сплачує цим платежем; округлення — до копійки.
     */
    public static function amountFor(int $base, int $percent): int
    {
        return intdiv($base * $percent + 50, 100);
    }

    public static function maxFor(int $base): int
    {
        return self::amountFor($base, max(0, (int) self::get('max_percent')));
    }

    private static function get(string $key): mixed
    {
        $stored = (array) SiteContent::setting('tips', []);

        return $stored[$key] ?? config("seatly.tips.{$key}");
    }
}
