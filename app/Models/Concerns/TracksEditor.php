<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Записує, хто з персоналу востаннє змінив запис (поле updated_by); коли — видно з updated_at.
 */
trait TracksEditor
{
    public static function bootTracksEditor(): void
    {
        static::saving(function (self $model): void {
            if (Auth::check()) {
                $model->setAttribute('updated_by', Auth::id());
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
