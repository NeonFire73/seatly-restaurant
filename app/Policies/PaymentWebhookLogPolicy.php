<?php

namespace App\Policies;

use App\Models\PaymentWebhookLog;
use App\Models\User;

/**
 * Журнал вебхуків (частина 2, п. 7.1): лише читання й лише для власника.
 */
class PaymentWebhookLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, PaymentWebhookLog $log): bool
    {
        return $user->isOwner();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PaymentWebhookLog $log): bool
    {
        return false;
    }

    public function delete(User $user, PaymentWebhookLog $log): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
