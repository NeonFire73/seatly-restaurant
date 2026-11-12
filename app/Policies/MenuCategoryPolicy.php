<?php

namespace App\Policies;

use App\Models\MenuCategory;
use App\Models\User;

class MenuCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManagement();
    }

    public function view(User $user, MenuCategory $category): bool
    {
        return $user->isManagement();
    }

    public function create(User $user): bool
    {
        return $user->isManagement();
    }

    public function update(User $user, MenuCategory $category): bool
    {
        return $user->isManagement();
    }

    /**
     * Категорію зі стравами видалити не можна: страви, зокрема м'яко видалені, посилаються на неї зі старих рахунків.
     */
    public function delete(User $user, MenuCategory $category): bool
    {
        return $user->isManagement()
            && $category->dishes()->withTrashed()->doesntExist();
    }

    public function reorder(User $user): bool
    {
        return $user->isManagement();
    }
}
