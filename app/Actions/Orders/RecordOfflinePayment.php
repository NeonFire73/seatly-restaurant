<?php

namespace App\Actions\Orders;

use App\Actions\Payments\InvalidatePendingPayments;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\Orders\OrderException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Офіціант фіксує оплату готівкою чи через термінал (частина 2, п. 4.1).
 * Грошей система не рухає: це лише запис, після якого замовлення можна закрити.
 */
class RecordOfflinePayment
{
    public function __construct(private InvalidatePendingPayments $invalidatePending) {}

    /**
     * @param  int|null  $amount  сума в копійках; без неї гасить усю суму до сплати
     */
    public function handle(Order $order, PaymentMethod $method, User $by, ?int $amount = null): Payment
    {
        if ($method === PaymentMethod::Online) {
            throw new OrderException('Онлайн-оплату гість проводить сам зі сторінки столика.');
        }

        $payment = DB::transaction(function () use ($order, $method, $by, $amount): Payment {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Open) {
                throw new OrderException('Оплату можна прийняти лише за відкритим замовленням.');
            }

            $locked->recalculateSubtotal();
            $due = $locked->amountDue();

            if ($due === 0) {
                throw new OrderException('За замовленням немає суми до сплати.');
            }

            $amount ??= $due;

            if ($amount < 1 || $amount > $due) {
                throw new OrderException('Сума оплати має бути більшою за нуль і не перевищувати суму до сплати.');
            }

            $payment = $locked->payments()->create([
                'method' => $method,
                'amount' => $amount,
                'status' => PaymentStatus::Success,
                'waiter_id' => $locked->waiter_id,
                'paid_at' => now(),
                'recorded_by' => $by->id,
            ]);

            $locked->recalculatePaidTotal();
            $order->refresh();

            return $payment;
        });

        // Гість міг відкрити платіжну сторінку й не заплатити: рахунок, що лишився чинним, оплатили б удруге
        $this->invalidatePending->handle($order);

        return $payment;
    }
}
