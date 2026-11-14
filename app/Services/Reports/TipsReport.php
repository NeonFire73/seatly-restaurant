<?php

namespace App\Services\Reports;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Звіт по чайових (частина 2, розділ 5): за період, по офіціантах, із кількістю платежів і середнім відсотком.
 * Враховуються лише успішні платежі з чайовими; повернені платежі у звіт не входять.
 */
final class TipsReport
{
    /**
     * @param  CarbonImmutable  $from  початок першого дня періоду в поясі закладу
     * @param  CarbonImmutable  $to  початок останнього дня періоду в поясі закладу
     * @param  int|null  $waiterId  лише цей офіціант (офіціант бачить тільки свої чайові)
     * @return list<array{waiter: string, payments: int, tips: int, base: int, percent: float}>
     */
    public static function rows(CarbonImmutable $from, CarbonImmutable $to, ?int $waiterId = null): array
    {
        $query = Payment::query()
            ->where('status', PaymentStatus::Success)
            ->where('tip_amount', '>', 0)
            ->whereBetween('paid_at', [$from->startOfDay()->utc(), $to->endOfDay()->utc()])
            ->selectRaw('waiter_id, count(*) as payments_count, sum(tip_amount) as tips_total, sum(amount) as base_total')
            ->groupBy('waiter_id');

        if ($waiterId !== null) {
            $query->where('waiter_id', $waiterId);
        }

        $totals = $query->toBase()->get();
        $names = User::whereIn('id', $totals->pluck('waiter_id')->filter())->pluck('name', 'id');
        $rows = [];

        foreach ($totals as $total) {
            $tips = (int) $total->tips_total;
            $base = (int) $total->base_total;

            $rows[] = [
                // Чайові за замовленням, яке вів менеджер: офіціанта на момент оплати не було
                'waiter' => $names[$total->waiter_id] ?? 'Без офіціанта',
                'payments' => (int) $total->payments_count,
                'tips' => $tips,
                'base' => $base,
                'percent' => self::percent($tips, $base),
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['tips'] <=> $a['tips']);

        return $rows;
    }

    /**
     * Середній відсоток — чайові до суми рахунків, з яких їх залишили.
     */
    public static function percent(int $tips, int $base): float
    {
        if ($base === 0) {
            return 0.0;
        }

        return round($tips / $base * 100, 1);
    }
}
