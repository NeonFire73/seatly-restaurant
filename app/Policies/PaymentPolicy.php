<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Платежі (частина 2, пп. 7.1 і 7.7): розділ лише для читання, бачать його власник і менеджер.
 * Змінюють платіж тільки дані провайдера й дія повернення коштів.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManagement();
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->isManagement();
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $user->isManagement();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
