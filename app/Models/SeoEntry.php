<?php

namespace App\Models;

use App\Models\Concerns\FlushesSiteContent;
use App\Models\Concerns\HasImageVariants;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

#[Fillable(['page_key', 'title', 'description', 'og_image'])]
class SeoEntry extends Model
{
    use FlushesSiteContent, HasImageVariants, HasTranslations, TracksEditor;

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

    /** @var list<string> */
    protected array $imageAttributes = ['og_image'];

    public function label(): string
    {
        return config("seatly.seo_pages.{$this->page_key}", $this->page_key);
    }
}
