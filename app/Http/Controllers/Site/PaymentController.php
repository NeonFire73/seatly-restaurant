<?php

namespace App\Http\Controllers\Site;

use App\Enums\OrderItemStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Сторінка результату оплати й квитанція (частина 1, п. 5.6). Платіж адресується ULID, а не номером.
 */
class PaymentController extends Controller
{
    public function result(Payment $payment): View
    {
        abort_unless($payment->isOnline(), 404);

        return view('site.payment-return', ['payment' => $payment]);
    }

    public function receipt(Payment $payment): View
    {
        abort_unless($payment->isOnline() && $payment->status === PaymentStatus::Success, 404);

        $payment->load(['reservation', 'order.table', 'order.items' => function (HasMany $relation): void {
            $relation->getQuery()->where('status', '!=', OrderItemStatus::Cancelled)->orderBy('id');
        }]);

        return view('site.payment-receipt', ['payment' => $payment]);
    }
}
