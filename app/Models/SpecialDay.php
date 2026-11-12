<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'is_closed', 'opens_at', 'closes_at', 'note'])]
class SpecialDay extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_closed' => 'boolean',
        ];
    }
}
