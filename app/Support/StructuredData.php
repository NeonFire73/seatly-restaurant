<?php

namespace App\Support;

use App\Models\Dish;
use App\Models\MenuCategory;
use App\Models\WorkingHour;
use App\Services\SiteContent;

/**
 * Мікророзмітка schema.org у форматі JSON-LD (частина 1, п. 7.4): `Restaurant` на головній, `Menu` на сторінці меню.
 * Дані ті самі, що гість бачить на сторінці: контакти й години з адмінки, страви з меню.
 */
final class StructuredData
{
    private const WEEKDAYS = [
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function restaurant(): array
    {
        $contacts = SiteContent::contacts();

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Restaurant',
            'name' => config('app.name'),
            'url' => Localization::route('home'),
            'description' => SiteContent::seo('home')->description ?? __('site.tagline'),
            'telephone' => $contacts['phone'],
            'email' => $contacts['email'],
            'priceRange' => '₴₴',
            'servesCuisine' => __('site.schema.cuisine'),
            'hasMenu' => Localization::route('menu'),
            'acceptsReservations' => Localization::route('booking'),
            'sameAs' => array_values(array_filter($contacts['socials'])),
            'openingHoursSpecification' => self::openingHours(),
        ];

        $address = SiteContent::address();

        if ($address !== null) {
            $data['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $address, 'addressCountry' => 'UA'];
        }

        if ($contacts['lat'] !== null && $contacts['lng'] !== null) {
            $data['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $contacts['lat'], 'longitude' => $contacts['lng']];
        }

        return self::clean($data);
    }

    /**
     * @return array<string, mixed>
     */
    public static function menu(): array
    {
        $categories = MenuCategory::query()
            ->where('is_active', true)
            ->with(['dishes' => fn ($query) => $query->where('is_available', true)->orderBy('sort')])
            ->orderBy('sort')
            ->get();

        $sections = [];

        foreach ($categories as $category) {
            if ($category->dishes->isEmpty()) {
                continue;
            }

            $sections[] = [
                '@type' => 'MenuSection',
                'name' => (string) $category->name,
                'hasMenuItem' => $category->dishes->map(fn (Dish $dish) => self::clean([
                    '@type' => 'MenuItem',
                    'name' => (string) $dish->name,
                    'description' => (string) $dish->description,
                    'image' => Images::url($dish->image),
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => number_format($dish->price / 100, 2, '.', ''),
                        'priceCurrency' => 'UAH',
                    ],
                ]))->all(),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Menu',
            'name' => __('site.menu.title'),
            'url' => Localization::route('menu'),
            'inLanguage' => app()->getLocale(),
            'hasMenuSection' => $sections,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private static function openingHours(): array
    {
        $hours = [];

        /** @var WorkingHour $day */
        foreach (SiteContent::workingHours() as $day) {
            if ($day->is_closed || $day->opens_at === null || $day->closes_at === null) {
                continue;
            }

            $hours[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/'.self::WEEKDAYS[$day->weekday],
                'opens' => substr((string) $day->opens_at, 0, 5),
                'closes' => substr((string) $day->closes_at, 0, 5),
            ];
        }

        return $hours;
    }

    /**
     * Порожніх полів у розмітці бути не повинно: пошуковик вважає їх помилкою.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function clean(array $data): array
    {
        return array_filter($data, fn (mixed $value) => $value !== null && $value !== '' && $value !== []);
    }
}
