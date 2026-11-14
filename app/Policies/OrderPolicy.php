<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

/**
 * Замовлення (частина 2, п. 7.1): власник і менеджер бачать усі, офіціант у списку — лише свої.
 * На сторінці «Зал» офіціант обслуговує будь-який столик: колегу доводиться підміняти.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->worksInHall($user);
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->isManagement()) {
            return true;
        }

        return $user->hasRole(UserRole::Waiter) && $order->waiter_id === $user->id;
    }

    /**
     * Відкрити замовлення, додати чи подати страву, прийняти оплату, закрити — робота в залі.
     */
    public function serve(User $user): bool
    {
        return $this->worksInHall($user);
    }

    /**
     * Скасувати замовлення чи вже розпочату позицію (частина 2, п. 4.2).
     */
    public function cancel(User $user): bool
    {
        return $user->isManagement();
    }

    /**
     * Екран кухні: статуси «готується» й «готово».
     */
    public function cook(User $user): bool
    {
        return $user->hasRole(UserRole::Owner, UserRole::Manager, UserRole::Kitchen);
    }

    /**
     * Замовлення створюються й змінюються лише діями на сторінці «Зал», не формою ресурсу.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Order $order): bool
    {
        return false;
    }

    public function delete(User $user, Order $order): bool
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
