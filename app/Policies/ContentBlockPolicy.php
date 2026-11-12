<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\OwnerOnly;

/**
 * Набір блоків задає верстка, тому власник лише редагує наявні блоки, а не створює й не видаляє їх.
 */
class ContentBlockPolicy
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
