<?php

namespace App\Models\Concerns;

use App\Support\Images;

/**
 * Після збереження нового зображення створює WebP-копії; старий файл разом із копіями видаляє.
 * Модель перелічує поля із зображеннями у властивості $imageAttributes.
 */
trait HasImageVariants
{
    public static function bootHasImageVariants(): void
    {
        static::saved(function (self $model): void {
            foreach ($model->imageAttributes() as $attribute) {
                if (! $model->wasChanged($attribute) && ! $model->wasRecentlyCreated) {
                    continue;
                }

                $old = $model->getOriginal($attribute);

                if ($model->wasChanged($attribute) && filled($old)) {
                    Images::deleteWithVariants($old);
                }

                if (filled($model->getAttribute($attribute))) {
                    Images::generateVariants($model->getAttribute($attribute));
                }
            }
        });

        static::deleted(function (self $model): void {
            // Страви видаляються м'яко, і фото ще знадобиться після відновлення
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            foreach ($model->imageAttributes() as $attribute) {
                Images::deleteWithVariants($model->getAttribute($attribute));
            }
        });
    }

    /**
     * @return list<string>
     */
    public function imageAttributes(): array
    {
        // Через ?? не можна: для невідомої властивості Eloquent спробує знайти однойменний зв'язок
        if (property_exists($this, 'imageAttributes')) {
            return $this->imageAttributes;
        }

        return ['image'];
    }
}
