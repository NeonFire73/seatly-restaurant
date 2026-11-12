<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\OwnerOnly;

/**
 * Оферта й політика мають фіксовані адреси на сайті, тому сторінки лише редагуються.
 */
class PagePolicy
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
