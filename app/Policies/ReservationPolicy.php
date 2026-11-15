<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\User;

/**
 * Бронювання (частина 2, п. 7.1): власник і менеджер керують, офіціант переглядає й садить гостей, кухня не бачить.
 */
class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->worksInHall($user);
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return $this->worksInHall($user);
    }

    public function create(User $user): bool
    {
        return $user->isManagement();
    }

    /**
     * Зміна контактів гостя й перенесення на інший столик чи час.
     */
    public function update(User $user, Reservation $reservation): bool
    {
        return $user->isManagement();
    }

    /**
     * Підтвердити неоплачене бронювання без оплати.
     */
    public function confirm(User $user, Reservation $reservation): bool
    {
        return $user->isManagement();
    }

    public function seat(User $user, Reservation $reservation): bool
    {
        return $this->worksInHall($user);
    }

    public function complete(User $user, Reservation $reservation): bool
    {
        return $this->worksInHall($user);
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        return $user->isManagement();
    }

    public function markNoShow(User $user, Reservation $reservation): bool
    {
        return $user->isManagement();
    }

    /**
     * Повернути «не прийшов» у «підтверджено».
     */
    public function restoreStatus(User $user, Reservation $reservation): bool
    {
        return $user->isManagement();
    }

    /**
     * Бронювання не видаляються: скасовані й завершені лишаються в історії для звітів.
     */
    public function delete(User $user, Reservation $reservation): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function worksInHall(User $user): bool
    {
        return $user->hasRole(UserRole::Owner, UserRole::Manager, UserRole::Waiter);
    }
}
