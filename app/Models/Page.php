<?php

namespace App\Models;

use App\Models\Concerns\FlushesSiteContent;
use App\Models\Concerns\TracksEditor;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * Текстова сторінка (оферта, політика). Текст очищується від небезпечної розмітки під час кожного збереження,
 * тому в шаблоні його можна виводити як HTML.
 */
#[Fillable(['slug', 'title', 'body'])]
class Page extends Model
{
    use FlushesSiteContent, HasTranslations, TracksEditor;

    /** @var list<string> */
    public array $translatable = ['title', 'body'];

    protected static function booted(): void
    {
        static::saving(function (Page $page): void {
            $page->setTranslations('body', array_map(
                fn (?string $html) => RichText::clean($html),
                $page->getTranslations('body'),
            ));
        });
    }
}
