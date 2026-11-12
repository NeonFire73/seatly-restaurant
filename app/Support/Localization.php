<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Українська — без префікса (/menu), інші мови — з префіксом (/en/menu).
 * Кожен публічний маршрут зареєстровано двічі: `menu` і `en.menu`.
 */
final class Localization
{
    /**
     * @return list<string>
     */
    public static function locales(): array
    {
        return array_keys(config('seatly.locales'));
    }

    public static function main(): string
    {
        return self::locales()[0];
    }

    public static function isSupported(?string $locale): bool
    {
        return in_array($locale, self::locales(), true);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function route(string $name, array $parameters = [], ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === self::main()) {
            return route($name, $parameters);
        }

        return route("{$locale}.{$name}", $parameters);
    }

    /**
     * Адреса поточної сторінки іншою мовою — для перемикача й hreflang.
     */
    public static function currentUrlFor(string $locale): string
    {
        $route = Route::current();
        $name = $route?->getName();

        if ($route === null || $name === null) {
            return self::route('home', [], $locale);
        }

        $baseName = self::stripLocalePrefix($name);
        $targetName = $baseName;

        if ($locale !== self::main()) {
            $targetName = "{$locale}.{$baseName}";
        }

        // Службові сторінки (як /ui-kit) існують лише однією мовою — тоді ведемо на головну
        if (! Route::has($targetName)) {
            return self::route('home', [], $locale);
        }

        // Значення за замовчуванням (як slug оферти) вшиті в маршрут; у посилання вони потрапили б як ?slug=
        $parameters = array_diff_key($route->parameters(), $route->defaults);

        return self::route($baseName, $parameters, $locale);
    }

    public static function stripLocalePrefix(string $routeName): string
    {
        foreach (self::locales() as $locale) {
            if (str_starts_with($routeName, "{$locale}.")) {
                return substr($routeName, strlen($locale) + 1);
            }
        }

        return $routeName;
    }

    /**
     * Для сторінок помилок маршрут не знайдено, тож мова визначається за першим сегментом адреси.
     */
    public static function applyFromPath(Request $request): void
    {
        $segment = $request->segment(1);

        if ($segment !== self::main() && self::isSupported($segment)) {
            app()->setLocale((string) $segment);
        }
    }
}
