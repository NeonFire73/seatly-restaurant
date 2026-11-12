<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Розділ «Контент сайту» доступний лише власникові (частина 2, п. 7.1).
 */
trait OwnerOnly
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user): bool
    {
        return $user->isOwner();
    }
}
