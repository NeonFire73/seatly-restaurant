<?php

namespace Database\Seeders;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Booking\AvailabilityService;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\TipSettings;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Історія закритих замовлень і платежів за 30 днів (частина 2, п. 10.3): щоб розділ «Платежі»,
 * звіт по чайових і майбутній дашборд не були порожніми. Записи створюються напряму, без дій:
 * дії працюють лише з поточним часом і не дали б закрити замовлення заднім числом.
 * Набір детермінований — повторне наповнення чистої бази дає ті самі цифри.
 */
class OrderHistorySeeder extends Seeder
{
    private const DAYS = 30;

    // Чайові у відсотках для онлайн-оплат, по колу; гості лишають їх не завжди
    private const TIP_PERCENTS = [10, 0, 15, 10, 5, 0, 10, 15];

    // Час відкриття замовлень протягом дня
    private const HOURS = ['12:10', '13:00', '13:40', '15:20', '18:10', '19:00', '19:30', '20:15', '20:50'];

    public function run(AvailabilityService $availability): void
    {
        // Повторний запуск не має подвоювати історію
        if (Order::where('status', OrderStatus::Closed)->exists()) {
            return;
        }

        $dishes = Dish::where('is_available', true)->orderBy('id')->get()->values();
        $tables = DiningTable::where('is_active', true)->orderBy('id')->get()->values();
        $waiters = User::where('email', 'like', 'waiter%')->orderBy('id')->get()->values();

        if ($dishes->isEmpty() || $tables->isEmpty()) {
            return;
        }

        $today = LocalTime::now()->startOfDay();
        $counter = 0;

        foreach (range(self::DAYS, 1) as $daysAgo) {
            $day = $today->subDays($daysAgo);

            if ($availability->dayWindow($day) === null) {
                continue;
            }

            // У вихідні гостей більше
            $ordersToday = 5 + ($daysAgo % 3);

            if ($day->isWeekend()) {
                $ordersToday += 2;
            }

            foreach (array_slice(self::HOURS, 0, $ordersToday) as $slot => $time) {
                $openedAt = LocalTime::parse($day->format('Y-m-d'), $time);

                if ($openedAt === null) {
                    continue;
                }

                $waiter = null;

                if ($waiters->isNotEmpty()) {
                    $waiter = $waiters[($counter + $daysAgo) % $waiters->count()];
                }

                $this->createOrder(
                    $counter,
                    $tables[($counter * 4 + $slot) % $tables->count()],
                    $waiter,
                    $dishes,
                    $openedAt->utc(),
                );

                $counter++;
            }
        }
    }

    /**
     * @param  Collection<int, Dish>  $dishes
     */
    private function createOrder(int $counter, DiningTable $table, ?User $waiter, Collection $dishes, CarbonImmutable $openedAt): void
    {
        $closedAt = $openedAt->addMinutes(55 + ($counter % 5) * 10);

        $order = new Order;
        $order->forceFill([
            'table_id' => $table->id,
            'waiter_id' => $waiter?->id,
            'type' => OrderType::DineIn,
            'status' => OrderStatus::Closed,
            'opened_at' => $openedAt,
            'closed_at' => $closedAt,
            'created_at' => $openedAt,
            'updated_at' => $closedAt,
        ])->save();

        $subtotal = 0;

        foreach (range(0, 1 + $counter % 3) as $position) {
            $dish = $dishes[($counter * 7 + $position * 5) % $dishes->count()];
            $quantity = 1 + ($counter + $position) % 2;
            $subtotal += $dish->price * $quantity;

            $order->items()->make()->forceFill([
                'dish_id' => $dish->id,
                'name' => $dish->getTranslations('name'),
                'price' => $dish->price,
                'quantity' => $quantity,
                'status' => OrderItemStatus::Served,
                'added_by' => $waiter?->id,
                'created_at' => $openedAt->addMinutes(3),
                'updated_at' => $closedAt,
            ])->save();
        }

        $order->forceFill(['subtotal' => $subtotal, 'paid_total' => $subtotal])->saveQuietly();

        $this->createPayment($counter, $order, $subtotal, $closedAt->subMinutes(4));
    }

    private function createPayment(int $counter, Order $order, int $amount, CarbonImmutable $paidAt): void
    {
        // Три платежі з п'яти — онлайн за QR, решта — готівка й термінал
        $method = match ($counter % 5) {
            3 => PaymentMethod::Cash,
            4 => PaymentMethod::Terminal,
            default => PaymentMethod::Online,
        };

        $payment = new Payment;
        $payment->forceFill([
            'order_id' => $order->id,
            'method' => $method,
            'amount' => $amount,
            'status' => PaymentStatus::Success,
            'waiter_id' => $order->waiter_id,
            'paid_at' => $paidAt,
            'created_at' => $paidAt->subMinutes(1),
            'updated_at' => $paidAt,
        ]);

        if ($method === PaymentMethod::Online) {
            $payment->forceFill([
                'provider' => FakeGateway::NAME,
                'tip_amount' => TipSettings::amountFor($amount, self::TIP_PERCENTS[$counter % count(self::TIP_PERCENTS)]),
                'expires_at' => $paidAt->addMinutes(14),
                'provider_modified_at' => $paidAt,
                'meta' => ['locale' => 'uk'],
            ]);
            $payment->rememberStatus(PaymentStatus::Created, $paidAt->subMinutes(1));
            $payment->rememberStatus(PaymentStatus::Success, $paidAt);
        } else {
            $payment->recorded_by = $order->waiter_id;
        }

        $payment->save();

        if ($method === PaymentMethod::Online) {
            $payment->forceFill(['provider_invoice_id' => 'fake_'.strtolower($payment->id)])->save();
        }
    }
}
