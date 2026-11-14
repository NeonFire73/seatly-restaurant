<?php

namespace App\Services\Booking;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Тимчасове утримання столика, поки гість заповнює майстер бронювання (частина 1, п. 5.3).
 *
 * Це лише ввічливість до гостя: інші відвідувачі бачать столик зайнятим. Від подвійного бронювання
 * захищає не утримання, а транзакція з блокуванням у CreateReservation.
 *
 * Усі утримання лежать одним списком у кеші: їх небагато, а шукати треба за перетином інтервалів.
 */
final class TableHold
{
    private const KEY = 'booking.holds';

    /**
     * Один гість утримує лише один столик: нове утримання замінює попереднє.
     * Повертає час закінчення або null, якщо столик на цей час уже утримує хтось інший.
     */
    public function put(string $owner, int $tableId, CarbonInterface $startsAt, CarbonInterface $endsAt): ?CarbonImmutable
    {
        $expiresAt = CarbonImmutable::now()->addMinutes(BookingSettings::hold());

        return Cache::lock(self::KEY.'.lock', 5)->block(3, function () use ($owner, $tableId, $startsAt, $endsAt, $expiresAt): ?CarbonImmutable {
            $holds = array_filter($this->all(fresh: true), fn (array $hold) => $hold['owner'] !== $owner);

            foreach ($holds as $hold) {
                if ($hold['table_id'] === $tableId && $this->overlaps($hold, $startsAt, $endsAt)) {
                    $this->store($holds);

                    return null;
                }
            }

            $holds[] = [
                'owner' => $owner,
                'table_id' => $tableId,
                'starts' => $startsAt->getTimestamp(),
                'ends' => $endsAt->getTimestamp(),
                'expires' => $expiresAt->getTimestamp(),
            ];

            $this->store($holds);

            return $expiresAt;
        });
    }

    public function release(string $owner): void
    {
        Cache::lock(self::KEY.'.lock', 5)->block(3, function () use ($owner): void {
            $this->store(array_filter($this->all(fresh: true), fn (array $hold) => $hold['owner'] !== $owner));
        });
    }

    /**
     * Чи утримує столик на цей час хтось, крім $exceptOwner.
     */
    public function isHeld(int $tableId, CarbonInterface $startsAt, CarbonInterface $endsAt, ?string $exceptOwner = null): bool
    {
        foreach ($this->all() as $hold) {
            if ($hold['table_id'] === $tableId && $hold['owner'] !== $exceptOwner && $this->overlaps($hold, $startsAt, $endsAt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Чинне утримання гостя або null, якщо воно вже закінчилося.
     *
     * @return array{owner: string, table_id: int, starts: int, ends: int, expires: int}|null
     */
    public function of(string $owner): ?array
    {
        foreach ($this->all() as $hold) {
            if ($hold['owner'] === $owner) {
                return $hold;
            }
        }

        return null;
    }

    /**
     * Перевірки для показу (isHeld викликається для кожної пари «слот × столик») читають список
     * один раз за запит; зміна під блокуванням ($fresh) завжди бере актуальний список із кешу.
     *
     * @return list<array{owner: string, table_id: int, starts: int, ends: int, expires: int}>
     */
    private function all(bool $fresh = false): array
    {
        $now = now()->getTimestamp();

        if ($fresh) {
            $stored = Cache::get(self::KEY, []);
        } else {
            $stored = Cache::memo()->get(self::KEY, []);
        }

        return array_values(array_filter(
            (array) $stored,
            fn (array $hold) => $hold['expires'] > $now,
        ));
    }

    /**
     * @param  array<int, array{owner: string, table_id: int, starts: int, ends: int, expires: int}>  $holds
     */
    private function store(array $holds): void
    {
        // Запис через memo() заразом скидає значення, запам'ятоване в цьому запиті
        Cache::memo()->put(self::KEY, array_values($holds), now()->addHours(2));
    }

    /**
     * @param  array{starts: int, ends: int}  $hold
     */
    private function overlaps(array $hold, CarbonInterface $startsAt, CarbonInterface $endsAt): bool
    {
        $buffer = BookingSettings::buffer() * 60;

        return $hold['starts'] < $endsAt->getTimestamp() + $buffer
            && $startsAt->getTimestamp() < $hold['ends'] + $buffer;
    }
}
