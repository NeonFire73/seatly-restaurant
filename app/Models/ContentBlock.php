<?php

namespace App\Models;

use App\Models\Concerns\FlushesSiteContent;
use App\Models\Concerns\HasImageVariants;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

#[Fillable(['key', 'title', 'subtitle', 'body', 'button_label', 'button_url', 'image', 'is_visible'])]
class ContentBlock extends Model
{
    use FlushesSiteContent, HasImageVariants, HasTranslations, TracksEditor;

    /** @var list<string> */
    public array $translatable = ['title', 'subtitle', 'body', 'button_label'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
        ];
    }

    public function label(): string
    {
        return $this->definition()['label'] ?? $this->key;
    }

    public function usesField(string $field): bool
    {
        return in_array($field, $this->definition()['fields'] ?? [], true);
    }

    /**
     * Ключі блоків містять крапку (home.hero), тому config() з крапковим шляхом тут не підходить.
     *
     * @return array{label?: string, fields?: list<string>}
     */
    private function definition(): array
    {
        return config('seatly.content_blocks')[$this->key] ?? [];
    }
}
