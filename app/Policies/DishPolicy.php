<?php

namespace App\Policies;

use App\Models\Dish;
use App\Models\User;

/**
 * Офіціант і кухня бачать меню й перемикають лише «є в наявності»; решту змінюють власник і менеджер.
 */
class DishPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Dish $dish): bool
    {
        return true;
    }

    public function toggleAvailability(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isManagement();
    }

    public function update(User $user, Dish $dish): bool
    {
        return $user->isManagement();
    }

    public function delete(User $user, Dish $dish): bool
    {
        return $user->isManagement();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isManagement();
    }

    public function restore(User $user, Dish $dish): bool
    {
        return $user->isManagement();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isManagement();
    }

    /**
     * Остаточне видалення зламало б позиції старих замовлень.
     */
    public function forceDelete(User $user, Dish $dish): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return $user->isManagement();
    }
}
