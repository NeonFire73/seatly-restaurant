<?php

use App\Support\Localization;

if (! function_exists('locale_route')) {
    /**
     * Адреса маршруту поточною мовою: locale_route('menu') → /menu або /en/menu.
     *
     * @param  array<string, mixed>  $parameters
     */
    function locale_route(string $name, array $parameters = [], ?string $locale = null): string
    {
        return Localization::route($name, $parameters, $locale);
    }
}
