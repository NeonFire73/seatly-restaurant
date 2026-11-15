<?php

namespace App\Services\Reports;

use App\Enums\OrderItemStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Цифри для дашборда (частина 2, п. 7.8). Дні й періоди рахуються в поясі закладу, у запити час іде в UTC.
 * Виторг — це сплачене за страви й за бронювання без чайових і без повернених сум.
 */
final class DashboardReport
{
    /**
     * Бронювання на сьогодні, які ще попереду або тривають.
     *
     * @return Collection<int, Reservation>
     */
    public static function todayReservations(): Collection
    {
        $today = LocalTime::now()->startOfDay();

        return Reservation::query()
            ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::Seated])
            ->whereBetween('starts_at', [$today->utc(), $today->endOfDay()->utc()])
            ->with('table')
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @return array{busy: int, total: int, percent: int}
     */
    public static function occupancy(): array
    {
        $total = DiningTable::where('is_active', true)->count();
        $busy = Order::query()->atTable()->distinct()->count('table_id');
        $percent = 0;

        if ($total > 0) {
            $percent = (int) round(min($busy, $total) / $total * 100);
        }

        return ['busy' => $busy, 'total' => $total, 'percent' => $percent];
    }

    /**
     * Виторг за останні $days днів разом із сьогоднішнім.
     */
    public static function revenue(int $days): int
    {
        return array_sum(self::revenueByDay($days));
    }

    /**
     * @return array<string, int> дата «Y-m-d» у поясі закладу => виторг у копійках; дні без оплат теж є
     */
    public static function revenueByDay(int $days): array
    {
        $byDay = self::emptyDays($days);

        foreach (self::payments($days) as $payment) {
            $day = LocalTime::of($payment->paid_at)->format('Y-m-d');

            if (isset($byDay[$day])) {
                $byDay[$day] += $payment->amount + $payment->booking_fee_amount - $payment->refunded_amount;
            }
        }

        return $byDay;
    }

    public static function tips(int $days): int
    {
        return (int) self::payments($days)->sum('tip_amount');
    }

    /**
     * Найпопулярніші страви за кількістю проданих порцій.
     *
     * @return list<array{name: string, quantity: int}>
     */
    public static function popularDishes(int $days, int $limit = 5): array
    {
        $totals = OrderItem::query()
            ->where('status', '!=', OrderItemStatus::Cancelled)
            ->where('created_at', '>=', self::since($days))
            ->selectRaw('dish_id, sum(quantity) as sold')
            ->groupBy('dish_id')
            ->orderByDesc('sold')
            ->limit($limit)
            ->toBase()
            ->get();

        // Страву могли прибрати з меню, а в статистиці продажів вона має лишитися
        $dishes = Dish::withTrashed()->whereIn('id', $totals->pluck('dish_id'))->get()->keyBy('id');
        $rows = [];

        foreach ($totals as $total) {
            $rows[] = [
                'name' => (string) ($dishes->get($total->dish_id)->name ?? '—'),
                'quantity' => (int) $total->sold,
            ];
        }

        return $rows;
    }

    /**
     * Частка неявок серед бронювань, час яких уже настав: «не прийшов» до суми «прийшов» і «не прийшов».
     *
     * @return array{no_shows: int, total: int, percent: float}
     */
    public static function noShowRate(int $days): array
    {
        $counts = Reservation::query()
            ->whereIn('status', [ReservationStatus::Seated, ReservationStatus::Completed, ReservationStatus::NoShow])
            ->whereBetween('starts_at', [self::since($days), now()])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        $noShows = (int) ($counts[ReservationStatus::NoShow->value] ?? 0);
        $total = (int) $counts->sum();
        $percent = 0.0;

        if ($total > 0) {
            $percent = round($noShows / $total * 100, 1);
        }

        return ['no_shows' => $noShows, 'total' => $total, 'percent' => $percent];
    }

    /**
     * Успішні платежі за період. Повністю повернені мають інший статус і сюди не входять,
     * а частково повернені лишаються успішними — їхня повернена сума віднімається у виторгу.
     *
     * @return Collection<int, Payment>
     */
    private static function payments(int $days): Collection
    {
        return Payment::query()
            ->where('status', PaymentStatus::Success)
            ->where('paid_at', '>=', self::since($days))
            ->get(['id', 'amount', 'booking_fee_amount', 'tip_amount', 'refunded_amount', 'paid_at']);
    }

    private static function since(int $days): CarbonImmutable
    {
        return LocalTime::now()->startOfDay()->subDays(max(1, $days) - 1)->utc();
    }

    /**
     * @return array<string, int>
     */
    private static function emptyDays(int $days): array
    {
        $first = LocalTime::now()->startOfDay()->subDays(max(1, $days) - 1);
        $byDay = [];

        for ($i = 0; $i < max(1, $days); $i++) {
            $byDay[$first->addDays($i)->format('Y-m-d')] = 0;
        }

        return $byDay;
    }
}
