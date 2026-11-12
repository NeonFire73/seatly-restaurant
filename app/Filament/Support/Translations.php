<?php

namespace App\Filament\Support;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Перекладні поля зберігаються як JSON {"uk": "...", "en": "..."} (spatie/laravel-translatable).
 * У формі кожна мова — окрема вкладка; поле на кшталт `name.uk` пише прямо в цей масив.
 */
final class Translations
{
    /**
     * @param  Closure(string $locale, bool $isMain): array<Component|Field>  $fields
     */
    public static function tabs(Closure $fields): Tabs
    {
        $locales = self::locales();
        $main = array_key_first($locales);

        $tabs = [];

        foreach ($locales as $locale => $label) {
            $tabs[] = Tab::make($label)
                ->key("locale-{$locale}")
                ->schema($fields($locale, $locale === $main));
        }

        return Tabs::make('Переклади')->tabs($tabs)->columnSpanFull();
    }

    /**
     * Пошук без урахування регістру одразу всіма мовами.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function search(Builder $query, string $column, string $search): Builder
    {
        $grammar = $query->getQuery()->getGrammar();
        $needle = '%'.mb_strtolower($search).'%';

        return $query->where(function (Builder $query) use ($grammar, $column, $needle): void {
            foreach (array_keys(self::locales()) as $locale) {
                $query->orWhereRaw('LOWER('.$grammar->wrap("{$column}->{$locale}").') LIKE ?', [$needle]);
            }
        });
    }

    /**
     * Варіанти для Select. pluck() у запиті тут не підходить: він повернув би сирий JSON усіх мов,
     * а потрібна назва поточною мовою, яку дає модель.
     *
     * @param  Builder<covariant Model>  $query
     * @return array<int|string, string>
     */
    public static function options(Builder $query, string $attribute = 'name'): array
    {
        return $query->get()
            ->mapWithKeys(fn (Model $model) => [$model->getKey() => (string) $model->getAttribute($attribute)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function locales(): array
    {
        return config('seatly.locales');
    }
}
