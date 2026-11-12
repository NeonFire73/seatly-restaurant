<?php

namespace App\Services\Booking;

use App\Exceptions\Booking\SlotUnavailable;
use App\Exceptions\Booking\TableUnavailable;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\SpecialDay;
use App\Services\SiteContent;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Розрахунок доступності (частина 2, п. 3.2): які слоти є на дату і які столики вільні на слот.
 *
 * Години роботи й слоти рахуються в поясі закладу; у запити до бази час іде в UTC —
 * Eloquent сам пояс не перетворює, тому всюди, де час потрапляє в запит, стоїть ->utc().
 */
class AvailabilityService
{
    public const STATE_FREE = 'free';

    public const STATE_BUSY = 'busy';

    // Столик не підходить за кількістю гостей: замалий або розрахований на більшу компанію
    public const STATE_UNFIT = 'unfit';

    /** @var array<string, array{0: CarbonImmutable, 1: CarbonImmutable}|null> */
    private array $windows = [];

    /** @var array<string, SpecialDay>|null */
    private ?array $specialDays = null;

    public function __construct(private readonly TableHold $holds) {}

    /**
     * Години роботи на дату: [відкриття, закриття] у поясі закладу або null, якщо зачинено.
     * Закриття «00:00» чи раніше за відкриття означає наступну добу.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function dayWindow(CarbonInterface $date): ?array
    {
        $day = LocalTime::of($date)->startOfDay();
        $key = $day->format('Y-m-d');

        if (! array_key_exists($key, $this->windows)) {
            $this->windows[$key] = $this->resolveWindow($day);
        }

        return $this->windows[$key];
    }

    /**
     * Чи можна бронювати на цю дату з сайту: заклад працює, дата в межах горизонту й ще лишилися слоти.
     */
    public function isBookableDate(CarbonInterface $date): bool
    {
        return $this->slotTimes($date) !== [];
    }

    /**
     * Початки слотів на дату для бронювання з сайту: сітка з кроком від відкриття,
     * не ближче за мінімальний час до візиту, не далі за горизонт, останній — заздалегідь до закриття.
     *
     * @return list<CarbonImmutable>
     */
    public function slotTimes(CarbonInterface $date): array
    {
        $window = $this->dayWindow($date);

        if ($window === null) {
            return [];
        }

        [$opens, $closes] = $window;
        $now = LocalTime::now();

        if ($opens->startOfDay()->gt($now->startOfDay()->addDays(BookingSettings::horizonDays()))) {
            return [];
        }

        $earliest = $now->addMinutes(BookingSettings::minLead());
        $latest = $closes->subMinutes(BookingSettings::lastSlotBeforeClose());
        $slots = [];

        for ($slot = $opens; $slot->lte($latest); $slot = $slot->addMinutes(BookingSettings::slotStep())) {
            if ($slot->gte($earliest)) {
                $slots[] = $slot;
            }
        }

        return $slots;
    }

    /**
     * Кінець бронювання: типова тривалість, але не пізніше закриття.
     */
    public function endsAt(CarbonInterface $startsAt): CarbonImmutable
    {
        $start = LocalTime::of($startsAt);
        $end = $start->addMinutes(BookingSettings::duration());
        $window = $this->windowContaining($start);

        if ($window !== null && $end->gt($window[1])) {
            return $window[1];
        }

        return $end;
    }

    /**
     * Слоти на дату для N гостей; слот доступний, якщо є хоча б один відповідний вільний столик.
     *
     * @return list<array{time: string, available: bool}>
     */
    public function slots(CarbonInterface $date, int $guests, ?string $holdOwner = null): array
    {
        $times = $this->slotTimes($date);

        if ($times === []) {
            return [];
        }

        $tables = $this->bookableTables()->filter(fn (DiningTable $table) => $this->fits($table, $guests));
        $reservations = $this->liveReservations($tables->modelKeys(), $times[0], $this->endsAt(end($times)));
        $slots = [];

        foreach ($times as $start) {
            $end = $this->endsAt($start);
            $available = false;

            foreach ($tables as $table) {
                if ($this->isFreeAmong($reservations, $table->id, $start, $end) && ! $this->holds->isHeld($table->id, $start, $end, $holdOwner)) {
                    $available = true;
                    break;
                }
            }

            $slots[] = ['time' => $start->format('H:i'), 'available' => $available];
        }

        return $slots;
    }

    /**
     * Стан кожного активного столика на слот: вільний, зайнятий або не підходить за кількістю гостей.
     *
     * @return array<int, string> ідентифікатор столика → STATE_*
     */
    public function tableStates(CarbonInterface $startsAt, int $guests, ?string $holdOwner = null, ?int $ignoreReservationId = null): array
    {
        $start = LocalTime::of($startsAt);
        $end = $this->endsAt($start);
        $tables = $this->bookableTables();
        // Під час перенесення саме бронювання не має заважати власному столику
        $reservations = $this->liveReservations($tables->modelKeys(), $start, $end)
            ->reject(fn (Reservation $reservation) => $reservation->id === $ignoreReservationId);
        $states = [];

        foreach ($tables as $table) {
            if (! $this->fits($table, $guests)) {
                $states[$table->id] = self::STATE_UNFIT;
            } elseif (! $this->isFreeAmong($reservations, $table->id, $start, $end) || $this->holds->isHeld($table->id, $start, $end, $holdOwner)) {
                $states[$table->id] = self::STATE_BUSY;
            } else {
                $states[$table->id] = self::STATE_FREE;
            }
        }

        return $states;
    }

    /**
     * Чи вільний столик на інтервал (з урахуванням перерви між бронюваннями).
     * Перевірка йде запитом до бази, тож усередині транзакції з блокуванням бачить актуальний стан.
     */
    public function isFree(DiningTable $table, CarbonInterface $startsAt, CarbonInterface $endsAt, ?int $ignoreReservationId = null): bool
    {
        $buffer = BookingSettings::buffer();

        return Reservation::query()
            ->live()
            ->where('table_id', $table->id)
            ->where('starts_at', '<', LocalTime::of($endsAt)->addMinutes($buffer)->utc())
            ->where('ends_at', '>', LocalTime::of($startsAt)->subMinutes($buffer)->utc())
            ->when($ignoreReservationId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreReservationId))
            ->doesntExist();
    }

    /**
     * Усі перевірки перед створенням чи перенесенням бронювання. Кидає доменний виняток.
     *
     * З сайту час має бути одним зі слотів; персонал може бронювати на будь-який час у години роботи
     * (гість подзвонив і прийде за двадцять хвилин).
     */
    public function assertBookable(
        DiningTable $table,
        CarbonInterface $startsAt,
        int $guests,
        bool $fromSite,
        ?int $ignoreReservationId = null,
        ?string $holdOwner = null,
    ): void {
        $start = LocalTime::of($startsAt);

        if ($fromSite) {
            $onGrid = false;

            foreach ($this->slotTimes($start) as $slot) {
                if ($slot->eq($start)) {
                    $onGrid = true;
                    break;
                }
            }

            if (! $onGrid) {
                throw new SlotUnavailable(__('booking.errors.slot'));
            }
        } elseif ($this->windowContaining($start) === null || $start->lt(LocalTime::now()->subMinutes(BookingSettings::slotStep()))) {
            throw new SlotUnavailable(__('booking.errors.slot'));
        }

        if (! $table->is_active || ! $table->hall->is_active || ! $this->fits($table, $guests)) {
            throw new TableUnavailable(__('booking.errors.table_unfit'));
        }

        $end = $this->endsAt($start);

        if (! $this->isFree($table, $start, $end, $ignoreReservationId)) {
            throw new TableUnavailable(__('booking.errors.table_taken'));
        }

        if ($fromSite && $this->holds->isHeld($table->id, $start, $end, $holdOwner)) {
            throw new TableUnavailable(__('booking.errors.table_taken'));
        }
    }

    /**
     * Найменший вільний столик, що підходить: великі столи лишаються для великих компаній.
     */
    public function suggestTable(CarbonInterface $startsAt, int $guests, ?string $holdOwner = null): ?DiningTable
    {
        $states = $this->tableStates($startsAt, $guests, $holdOwner);

        return $this->bookableTables()
            ->filter(fn (DiningTable $table) => $states[$table->id] === self::STATE_FREE)
            ->sortBy(['seats', 'id'])
            ->first();
    }

    /**
     * Активні столики активних залів.
     *
     * @return Collection<int, DiningTable>
     */
    public function bookableTables(): Collection
    {
        return DiningTable::query()
            ->where('is_active', true)
            ->whereHas('hall', fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('id')
            ->get();
    }

    public function fits(DiningTable $table, int $guests): bool
    {
        return $guests >= $table->min_guests && $guests <= $table->seats;
    }

    /**
     * Найбільша компанія, яку можна посадити за один столик.
     */
    public function maxGuests(): int
    {
        $seats = (int) $this->bookableTables()->max('seats');

        return max(1, min($seats, BookingSettings::maxGuests()));
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function resolveWindow(CarbonImmutable $day): ?array
    {
        // Особливих днів небагато, а календар майстра питає про кожен день місяця — тож читаємо всі одразу
        $this->specialDays ??= SpecialDay::all()->keyBy(fn (SpecialDay $special) => $special->date->format('Y-m-d'))->all();
        $special = $this->specialDays[$day->format('Y-m-d')] ?? null;

        if ($special !== null) {
            if ($special->is_closed || $special->opens_at === null || $special->closes_at === null) {
                return null;
            }

            return $this->buildWindow($day, (string) $special->opens_at, (string) $special->closes_at);
        }

        $hours = SiteContent::workingHours()->firstWhere('weekday', $day->dayOfWeekIso);

        if ($hours === null || $hours->is_closed || $hours->opens_at === null || $hours->closes_at === null) {
            return null;
        }

        return $this->buildWindow($day, (string) $hours->opens_at, (string) $hours->closes_at);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function buildWindow(CarbonImmutable $day, string $opensAt, string $closesAt): array
    {
        $opens = $day->setTimeFromTimeString(substr($opensAt, 0, 5));
        $closes = $day->setTimeFromTimeString(substr($closesAt, 0, 5));

        if ($closes->lte($opens)) {
            $closes = $closes->addDay();
        }

        return [$opens, $closes];
    }

    /**
     * Робочий день, у який потрапляє момент; після півночі це може бути день, що почався вчора.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function windowContaining(CarbonImmutable $moment): ?array
    {
        foreach ([$moment, $moment->subDay()] as $day) {
            $window = $this->dayWindow($day);

            if ($window !== null && $moment->gte($window[0]) && $moment->lt($window[1])) {
                return $window;
            }
        }

        return null;
    }

    /**
     * «Живі» бронювання столиків, які можуть перетнутися з інтервалом.
     *
     * @param  array<int, int|string>  $tableIds
     * @return Collection<int, Reservation>
     */
    private function liveReservations(array $tableIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $buffer = BookingSettings::buffer();

        return Reservation::query()
            ->live()
            ->whereIn('table_id', $tableIds)
            ->where('starts_at', '<', $to->addMinutes($buffer)->utc())
            ->where('ends_at', '>', $from->subMinutes($buffer)->utc())
            ->get(['id', 'table_id', 'starts_at', 'ends_at']);
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     */
    private function isFreeAmong(Collection $reservations, int $tableId, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        $buffer = BookingSettings::buffer() * 60;

        foreach ($reservations as $reservation) {
            if ($reservation->table_id === $tableId
                && $reservation->starts_at->getTimestamp() < $end->getTimestamp() + $buffer
                && $reservation->ends_at->getTimestamp() + $buffer > $start->getTimestamp()) {
                return false;
            }
        }

        return true;
    }
}
