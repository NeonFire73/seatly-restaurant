<?php

namespace App\Models;

use App\Models\Concerns\FlushesSiteContent;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Налаштування закладу: ключ — значення JSON. Читаються через SiteContent::setting() (з кешем).
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    use FlushesSiteContent, TracksEditor;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
