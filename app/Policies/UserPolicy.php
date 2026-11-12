<?php

namespace App\Policies;

use App\Models\User;

/**
 * Розділ «Персонал» — лише для власника.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isOwner();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isOwner();
    }

    /**
     * Себе видалити не можна, інакше заклад може лишитися без власника.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isOwner() && $user->isNot($model);
    }
}
