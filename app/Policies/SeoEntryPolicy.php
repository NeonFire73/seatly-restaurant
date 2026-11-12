<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\OwnerOnly;

/**
 * Перелік сторінок для SEO задано в config/seatly.php, тому записи лише редагуються.
 */
class SeoEntryPolicy
{
    use OwnerOnly;

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user): bool
    {
        return false;
    }
}
