<?php

namespace App\Models;

use App\Models\Concerns\FlushesSiteContent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['weekday', 'opens_at', 'closes_at', 'is_closed'])]
class WorkingHour extends Model
{
    use FlushesSiteContent;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_closed' => 'boolean',
        ];
    }
}
