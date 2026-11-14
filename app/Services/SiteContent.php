<?php

namespace App\Services;

use App\Models\ContentBlock;
use App\Models\GalleryImage;
use App\Models\Page;
use App\Models\SeoEntry;
use App\Models\Setting;
use App\Models\WorkingHour;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Вміст публічного сайту з бази. Усе читається один раз і кешується без терміну дії:
 * кеш скидається моделями під час збереження (трейт FlushesSiteContent).
 *
 * У кеш кладуться сирі атрибути, а не моделі: Laravel 13 не розсеріалізовує з кешу об'єкти
 * (cache.serializable_classes = false — захист на випадок витоку APP_KEY).
 */
final class SiteContent
{
    private const KEYS = ['blocks', 'gallery', 'pages', 'seo', 'settings', 'hours'];

    /** @var array<string, array{rows: array<int, array<string, mixed>>, models: Collection<int, Model>}> */
    private static array $hydrated = [];

    /**
     * Видимий блок або null — тоді сторінка просто не виводить цей блок.
     */
    public static function block(string $key): ?ContentBlock
    {
        /** @var ContentBlock|null $block */
        $block = self::models('blocks', ContentBlock::query())->keyBy('key')->get($key);

        if ($block === null || ! $block->is_visible) {
            return null;
        }

        return $block;
    }

    /**
     * @return Collection<int, GalleryImage>
     */
    public static function gallery(): Collection
    {
        return self::models('gallery', GalleryImage::where('is_visible', true)->orderBy('sort'));
    }

    public static function page(string $slug): ?Page
    {
        /** @var Page|null */
        return self::models('pages', Page::query())->keyBy('slug')->get($slug);
    }

    public static function seo(string $pageKey): ?SeoEntry
    {
        /** @var SeoEntry|null */
        return self::models('seo', SeoEntry::query())->keyBy('page_key')->get($pageKey);
    }

    public static function setting(string $key, mixed $default = null): mixed
    {
        /** @var Setting|null $setting */
        $setting = self::models('settings', Setting::query())->keyBy('key')->get($key);

        return $setting->value ?? $default;
    }

    /**
     * @return array{address: array<string, string>, phone: ?string, email: ?string, lat: ?float, lng: ?float, socials: array<string, ?string>}
     */
    public static function contacts(): array
    {
        $contacts = (array) self::setting('contacts', []);

        return [
            'address' => (array) ($contacts['address'] ?? []),
            'phone' => $contacts['phone'] ?? null,
            'email' => $contacts['email'] ?? null,
            'lat' => isset($contacts['lat']) && $contacts['lat'] !== '' ? (float) $contacts['lat'] : null,
            'lng' => isset($contacts['lng']) && $contacts['lng'] !== '' ? (float) $contacts['lng'] : null,
            'socials' => (array) ($contacts['socials'] ?? []),
        ];
    }

    /**
     * Адреса поточною мовою; якщо перекладу немає — основною.
     */
    public static function address(): ?string
    {
        $address = array_filter(self::contacts()['address']);

        return $address[app()->getLocale()] ?? $address[array_key_first(config('seatly.locales'))] ?? null;
    }

    /**
     * @return Collection<int, WorkingHour>
     */
    public static function workingHours(): Collection
    {
        return self::models('hours', WorkingHour::orderBy('weekday'));
    }

    public static function flush(): void
    {
        foreach (self::KEYS as $key) {
            Cache::memo()->forget("site-content.{$key}");
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Collection<int, TModel>
     */
    private static function models(string $key, Builder $query): Collection
    {
        // memo(): сховище кешу — база, а параметри бронювання читаються в циклах (сотні разів за запит),
        // тож у межах одного запиту значення береться з пам'яті, а не окремим SQL-запитом щоразу
        $rows = Cache::memo()->rememberForever(
            "site-content.{$key}",
            fn (): array => $query->get()->map(fn (Model $model) => $model->getAttributes())->all(),
        );

        // Моделі збираються з рядків один раз, доки рядки в кеші ті самі: після flush() чи в наступному
        // запиті memo() віддає інший масив, і колекція будується заново
        if (! isset(self::$hydrated[$key]) || self::$hydrated[$key]['rows'] !== $rows) {
            $prototype = $query->getModel();

            self::$hydrated[$key] = [
                'rows' => $rows,
                'models' => collect($rows)->values()->map(fn (array $attributes) => $prototype->newFromBuilder($attributes)),
            ];
        }

        return self::$hydrated[$key]['models'];
    }
}
