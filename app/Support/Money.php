<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Гроші зберігаються цілим числом у копійках. Перетворення робиться над рядком,
 * без float: 19.99 * 100 у double дає 1998.9999…, і після відкидання дробу губиться копійка.
 */
final class Money
{
    public static function toKopecks(string|int|float|null $hryvnias): int
    {
        if ($hryvnias === null || $hryvnias === '') {
            throw new InvalidArgumentException('Сума не може бути порожньою.');
        }

        $value = str_replace([' ', ','], ['', '.'], trim((string) $hryvnias));

        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException("Некоректна сума: {$hryvnias}");
        }

        $fraction = str_pad($matches[2] ?? '', 2, '0');

        return (int) $matches[1] * 100 + (int) $fraction;
    }

    /**
     * Значення для поля форми: 12050 → «120.50», 12000 → «120».
     */
    public static function toInput(?int $kopecks): ?string
    {
        if ($kopecks === null) {
            return null;
        }

        $hryvnias = intdiv($kopecks, 100);
        $rest = $kopecks % 100;

        if ($rest === 0) {
            return (string) $hryvnias;
        }

        return sprintf('%d.%02d', $hryvnias, $rest);
    }

    /**
     * Для показу людям мовою сторінки: uk — «1 250,50 ₴», en — «₴1,250.50».
     * Копійки не показуються, якщо сума ціла.
     */
    public static function format(int $kopecks, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $rest = $kopecks % 100;
        $decimals = 0;

        if ($rest !== 0) {
            $decimals = 2;
        }

        $amount = $kopecks / 100;

        if ($locale === 'en') {
            return '₴'.number_format($amount, $decimals, '.', ',');
        }

        return number_format($amount, $decimals, ',', ' ').' ₴';
    }
}
