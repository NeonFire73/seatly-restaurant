<?php

namespace App\Policies;

use App\Models\DiningTable;
use App\Models\User;

class DiningTablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManagement();
    }

    public function view(User $user, DiningTable $table): bool
    {
        return $user->isManagement();
    }

    public function create(User $user): bool
    {
        return $user->isManagement();
    }

    public function update(User $user, DiningTable $table): bool
    {
        return $user->isManagement();
    }

    /**
     * Столик з історією бронювань чи замовлень лише вимикається, щоб не втратити звіти.
     */
    public function delete(User $user, DiningTable $table): bool
    {
        return $user->isManagement()
            && $table->reservations()->doesntExist()
            && $table->orders()->doesntExist();
    }
}
