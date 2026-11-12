<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\OwnerOnly;

class GalleryImagePolicy
{
    use OwnerOnly;

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function delete(User $user): bool
    {
        return $user->isOwner();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function reorder(User $user): bool
    {
        return $user->isOwner();
    }
}
