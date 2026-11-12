<?php

namespace App\Models;

use App\Models\Concerns\FlushesSiteContent;
use App\Models\Concerns\HasImageVariants;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

#[Fillable(['image', 'alt', 'sort', 'is_visible'])]
class GalleryImage extends Model
{
    use FlushesSiteContent, HasImageVariants, HasTranslations, TracksEditor;

    /** @var list<string> */
    public array $translatable = ['alt'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
        ];
    }
}
