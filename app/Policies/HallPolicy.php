<?php

namespace App\Policies;

use App\Models\Hall;
use App\Models\User;

class HallPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManagement();
    }

    public function view(User $user, Hall $hall): bool
    {
        return $user->isManagement();
    }

    public function create(User $user): bool
    {
        return $user->isManagement();
    }

    public function update(User $user, Hall $hall): bool
    {
        return $user->isManagement();
    }

    /**
     * Зал зі столиками не видаляється; щоб прибрати його з сайту, його вимикають.
     */
    public function delete(User $user, Hall $hall): bool
    {
        return $user->isManagement() && $hall->tables()->doesntExist();
    }

    public function reorder(User $user): bool
    {
        return $user->isManagement();
    }
}
