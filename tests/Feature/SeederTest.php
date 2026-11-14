<?php

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Hall;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WorkingHour;
use App\Services\Reports\TipsReport;
use Database\Seeders\OrderHistorySeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkingHourSeeder;

it('наповнює базу демо-даними', function () {
    $this->seed();

    expect(Hall::count())->toBe(2)
        ->and(DiningTable::count())->toBe(15)
        ->and(DiningTable::pluck('qr_token')->unique())->toHaveCount(15)
        ->and(MenuCategory::count())->toBe(6)
        ->and(Dish::count())->toBeGreaterThanOrEqual(35)
        ->and(WorkingHour::count())->toBe(7)
        // Бронювання на найближчий тиждень, без накладок на одному столику
        ->and(Reservation::live()->where('starts_at', '>', now())->count())->toBeGreaterThan(20);

    foreach (UserRole::cases() as $role) {
        expect(User::where('role', $role)->exists())->toBeTrue();
    }
});

it('можна запускати повторно для користувачів і годин роботи', function () {
    $this->seed();
    $this->seed([UserSeeder::class, WorkingHourSeeder::class]);

    expect(User::count())->toBe(5)
        ->and(WorkingHour::count())->toBe(7);
});

it('сидер створює історію закритих замовлень і платежів за 30 днів', function () {
    $this->seed();

    $closed = Order::where('status', 'closed')->get();

    expect($closed->count())->toBeGreaterThan(80)
        ->and($closed->every(fn (Order $order) => $order->amountDue() === 0 && $order->subtotal > 0))->toBeTrue()
        ->and(Payment::where('method', PaymentMethod::Online)->where('tip_amount', '>', 0)->count())->toBeGreaterThan(20)
        ->and(Payment::where('method', PaymentMethod::Cash)->exists())->toBeTrue()
        ->and(Payment::where('method', PaymentMethod::Terminal)->exists())->toBeTrue()
        ->and(Payment::whereNotNull('provider_invoice_id')->pluck('provider_invoice_id')->unique()->count())
        ->toBe(Payment::whereNotNull('provider_invoice_id')->count())
        ->and(TipsReport::rows(now()->subDays(31)->toImmutable(), now()->toImmutable()))->not->toBeEmpty();

    // Сума замовлення збігається з позиціями й платежем
    $order = $closed->first();
    $order->recalculateSubtotal();
    $order->recalculatePaidTotal();

    expect($order->amountDue())->toBe(0)
        ->and($order->paid_total)->toBe($order->subtotal);

    // Повторний запуск історію не подвоює
    $count = Order::count();
    $this->seed(OrderHistorySeeder::class);

    expect(Order::count())->toBe($count);
});
