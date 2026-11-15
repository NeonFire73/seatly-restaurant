<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Localization;
use Illuminate\Http\Response;

/**
 * sitemap.xml і robots.txt (частина 1, п. 7.4). Обидва будуються з маршрутів, тож адреса сайту
 * береться з APP_URL, а нова мова з'являється в мапі без правок тут.
 */
class SeoController extends Controller
{
    // Сторінки, які мають бути в пошуку. Сторінки столиків, бронювань і оплати сюди не входять:
    // вони відкриваються за токеном і закриті від індексації
    private const PAGES = ['home', 'menu', 'booking', 'about', 'contacts', 'privacy', 'offer'];

    public function sitemap(): Response
    {
        $pages = [];

        foreach (self::PAGES as $page) {
            $alternates = [];

            foreach (Localization::locales() as $locale) {
                $alternates[$locale] = Localization::route($page, [], $locale);
            }

            $pages[] = $alternates;
        }

        return response()
            ->view('site.sitemap', ['pages' => $pages, 'main' => Localization::main()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *'];

        foreach (['/admin', '/t/', '/pay/', '/fake-pay/', '/webhooks/'] as $path) {
            $lines[] = "Disallow: {$path}";
        }

        // Сторінка бронювання гостя — /booking/{token}; сам майстер /booking лишається відкритим
        foreach (Localization::locales() as $locale) {
            $prefix = '';

            if ($locale !== Localization::main()) {
                $prefix = "/{$locale}";
                $lines[] = "Disallow: {$prefix}/t/";
                $lines[] = "Disallow: {$prefix}/pay/";
            }

            $lines[] = "Disallow: {$prefix}/booking/";
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
