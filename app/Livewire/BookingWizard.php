<?php

namespace App\Livewire;

use App\Actions\Payments\StartReservationPayment;
use App\Actions\Reservations\CreateReservation;
use App\Actions\Reservations\ReservationData;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exceptions\Booking\SlotUnavailable;
use App\Exceptions\Booking\TableUnavailable;
use App\Exceptions\Orders\OrderException;
use App\Exceptions\Payments\PaymentException;
use App\Livewire\Concerns\HasPreorderCart;
use App\Livewire\Concerns\KeepsPageLocale;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Reservation;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingSettings;
use App\Services\Booking\TableHold;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;
use Livewire\Component;

/**
 * Майстер бронювання (частина 1, п. 5.3): дата й час → столик → контакти → попереднє замовлення → підтвердження.
 * Якщо є що оплачувати (плата за бронювання чи страви), після підтвердження гість іде на платіжну сторінку.
 *
 * Стан зберігається в сесії (#[Session]), тому переживає оновлення сторінки.
 * Жодному значенню з браузера не довіряємо: кожен крок перевіряє його на сервері ще раз.
 *
 * @property-read list<list<array{date: string, day: int, inMonth: bool, bookable: bool, today: bool}>> $calendar
 * @property-read list<array{time: string, available: bool}> $timeSlots
 * @property-read Collection<int, Hall> $halls
 * @property-read array<int, string> $tableStates
 * @property-read DiningTable|null $table
 * @property-read Reservation|null $doneReservation
 */
class BookingWizard extends Component
{
    use HasPreorderCart, KeepsPageLocale;

    public const STEPS = ['when', 'table', 'contacts', 'preorder', 'confirm'];

    #[Session]
    public int $step = 1;

    #[Session]
    public int $guests = 2;

    #[Session]
    public ?string $date = null;

    #[Session]
    public ?string $time = null;

    // Місяць, який показує календар: «2026-11»
    #[Session]
    public string $month = '';

    #[Session]
    public ?int $hallId = null;

    #[Session]
    public ?int $tableId = null;

    #[Session]
    public string $name = '';

    #[Session]
    public string $phone = '';

    #[Session]
    public string $email = '';

    #[Session]
    public string $comment = '';

    #[Session]
    public bool $consent = false;

    #[Session]
    public bool $wantsPreorder = false;

    /** @var array<int, int> кошик попереднього замовлення: ідентифікатор страви => кількість */
    #[Session]
    public array $cart = [];

    // Випадковий ідентифікатор гостя для утримання столика; з браузера його змінити не можна
    #[Locked]
    #[Session]
    public string $holdOwner = '';

    // Коли закінчиться утримання (мітка часу) — для таймера на екрані
    #[Session]
    public ?int $holdExpiresAt = null;

    // Токен щойно створеного бронювання: показується екран успіху
    #[Locked]
    #[Session]
    public ?string $doneToken = null;

    public string $notice = '';

    private ?AvailabilityService $availability = null;

    public function mount(): void
    {
        if ($this->holdOwner === '') {
            $this->holdOwner = (string) Str::uuid();
        }

        $this->guests = max(1, min($this->guests, $this->maxGuests()));
        $this->restoreState();
    }

    public function decrementGuests(): void
    {
        $this->changeGuests($this->guests - 1);
    }

    public function incrementGuests(): void
    {
        $this->changeGuests($this->guests + 1);
    }

    public function previousMonth(): void
    {
        $this->changeMonth(-1);
    }

    public function nextMonth(): void
    {
        $this->changeMonth(1);
    }

    public function selectDate(string $date): void
    {
        $day = LocalTime::parse($date);

        if ($day === null || ! $this->availability()->isBookableDate($day)) {
            return;
        }

        $this->date = $date;
        $this->time = null;
        $this->dropTable();
    }

    public function selectTime(string $time): void
    {
        foreach ($this->timeSlots as $slot) {
            if ($slot['time'] === $time && $slot['available']) {
                $this->time = $time;
                $this->dropTable();

                return;
            }
        }
    }

    public function chooseTable(): void
    {
        $this->notice = '';

        if (! $this->slotIsAvailable()) {
            $this->time = null;
            $this->notice = __('booking.errors.pick_slot');

            return;
        }

        $this->step = 2;
        $this->hallId = $this->defaultHallId();
    }

    public function selectHall(int $hallId): void
    {
        if ($this->halls->contains('id', $hallId)) {
            $this->hallId = $hallId;
        }
    }

    public function selectTable(int $tableId): void
    {
        $this->notice = '';
        $start = $this->startsAt();

        if ($start === null || ($this->tableStates[$tableId] ?? null) !== AvailabilityService::STATE_FREE) {
            return;
        }

        $expiresAt = app(TableHold::class)->put($this->holdOwner, $tableId, $start, $this->availability()->endsAt($start));

        if ($expiresAt === null) {
            $this->notice = __('booking.errors.table_taken');
            unset($this->tableStates);

            return;
        }

        $this->tableId = $tableId;
        $this->holdExpiresAt = $expiresAt->getTimestamp();
        $table = DiningTable::find($tableId);

        if ($table !== null) {
            $this->hallId = $table->hall_id;
        }

        unset($this->tableStates);
    }

    public function autoSelect(): void
    {
        $start = $this->startsAt();

        if ($start === null) {
            return;
        }

        $table = $this->availability()->suggestTable($start, $this->guests, $this->holdOwner);

        if ($table === null) {
            $this->notice = __('booking.table.none_anywhere');

            return;
        }

        $this->selectTable($table->id);
    }

    public function enterContacts(): void
    {
        $this->notice = '';

        if (! $this->holdIsValid()) {
            $this->dropTable();
            $this->notice = __('booking.errors.pick_table');

            return;
        }

        $this->step = 3;
    }

    public function review(): void
    {
        $this->notice = '';
        $this->validateContacts();
        $this->step = 4;
    }

    public function choosePreorder(bool $wants): void
    {
        $this->notice = '';
        $this->wantsPreorder = $wants && $this->preorderAvailable();
    }

    public function toSummary(): void
    {
        $this->notice = '';

        if (! $this->preorderAvailable()) {
            $this->wantsPreorder = false;
        }

        if ($this->wantsPreorder && $this->cartLines === []) {
            $this->notice = __('booking.preorder.errors.empty');

            return;
        }

        $this->step = 5;
    }

    public function back(): void
    {
        $this->notice = '';
        $this->step = max(1, $this->step - 1);
    }

    /**
     * «Змінити» в підсумку: повернутися можна лише на вже пройдений крок.
     */
    public function goTo(int $step): void
    {
        if ($step >= 1 && $step < $this->step) {
            $this->notice = '';
            $this->step = $step;
        }
    }

    /**
     * Викликає таймер у браузері, коли час утримання столика вийшов.
     */
    public function holdExpired(): void
    {
        if ($this->tableId === null || $this->holdIsValid()) {
            return;
        }

        $this->dropTable();
        $this->step = min($this->step, 2);
        $this->notice = __('booking.table.hold_expired');
    }

    public function submit(CreateReservation $createReservation, StartReservationPayment $startPayment): void
    {
        $this->notice = '';
        $limiterKey = 'booking:'.request()->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            $this->notice = __('booking.errors.too_many');

            return;
        }

        $start = $this->startsAt();

        if ($start === null || $this->tableId === null) {
            $this->step = 1;
            $this->notice = __('booking.errors.pick_slot');

            return;
        }

        $this->validateContacts();
        $comment = trim($this->comment);

        if ($comment === '') {
            $comment = null;
        }

        $preorderItems = [];

        if ($this->wantsPreorder) {
            $preorderItems = $this->cartItems();

            // Страву зняли з меню, поки гість перевіряв дані: мовчки бронювати без неї не можна
            if (count($preorderItems) !== count($this->cart)) {
                $this->cart = $preorderItems;
                unset($this->cartLines);
                $this->step = 4;
                $this->notice = __('booking.preorder.errors.dish_unavailable');

                return;
            }
        }

        try {
            $reservation = $createReservation->handle(new ReservationData(
                tableId: $this->tableId,
                startsAt: $start,
                guests: $this->guests,
                name: trim($this->name),
                phone: (string) self::normalizePhone($this->phone),
                email: mb_strtolower(trim($this->email)),
                comment: $comment,
                locale: app()->getLocale(),
                source: ReservationSource::Site,
                holdOwner: $this->holdOwner,
                preorderItems: $preorderItems,
            ));
        } catch (TableUnavailable $e) {
            // Столик встигли зайняти: гість повертається до вибору столика, контакти збережено
            $this->dropTable();
            $this->step = 2;
            $this->notice = $e->getMessage();

            return;
        } catch (SlotUnavailable $e) {
            $this->dropTable();
            $this->time = null;
            $this->step = 1;
            $this->notice = $e->getMessage();

            return;
        } catch (OrderException $e) {
            // Попереднє замовлення не склалося (страву зняли з меню, до візиту лишилося замало часу):
            // бронювання не створено, гість повертається до кошика
            unset($this->preorderMenu, $this->cartLines);
            $this->step = 4;
            $this->notice = $e->getMessage();

            return;
        }

        RateLimiter::hit($limiterKey, 600);

        $this->reset('step', 'guests', 'date', 'time', 'hallId', 'tableId', 'name', 'phone', 'email', 'comment', 'consent', 'holdExpiresAt', 'wantsPreorder', 'cart');

        if ($reservation->status !== ReservationStatus::PendingPayment) {
            $this->doneToken = $reservation->token;

            return;
        }

        // Є що оплачувати: екран успіху гість побачить після оплати, на сторінці результату
        try {
            $payment = $startPayment->handle($reservation, app()->getLocale());
            $this->redirect((string) $payment->page_url);
        } catch (PaymentException) {
            // Бронювання створене й тримає столик: оплату можна почати ще раз зі сторінки бронювання
            $this->redirect(locale_route('reservation.show', ['token' => $reservation->token]));
        }
    }

    public function startOver(): void
    {
        $this->doneToken = null;
        $this->step = 1;
    }

    /**
     * Сітка календаря: тижні з понеділка, для кожного дня — чи можна на нього бронювати.
     *
     * @return list<list<array{date: string, day: int, inMonth: bool, bookable: bool, today: bool}>>
     */
    #[Computed]
    public function calendar(): array
    {
        $first = LocalTime::parse($this->month.'-01') ?? LocalTime::now()->startOfMonth();
        $cursor = $first->startOfWeek();
        $last = $first->endOfMonth()->endOfWeek();
        $today = LocalTime::now()->format('Y-m-d');
        $weeks = [];

        while ($cursor->lte($last)) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $inMonth = $cursor->month === $first->month;

                $week[] = [
                    'date' => $cursor->format('Y-m-d'),
                    'day' => $cursor->day,
                    'inMonth' => $inMonth,
                    'bookable' => $inMonth && $this->availability()->isBookableDate($cursor),
                    'today' => $cursor->format('Y-m-d') === $today,
                ];

                $cursor = $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return $weeks;
    }

    /**
     * Назва не `slots`: у Livewire 4 властивість $this->slots зайнята слотами компонента.
     *
     * @return list<array{time: string, available: bool}>
     */
    #[Computed]
    public function timeSlots(): array
    {
        $day = $this->day();

        if ($day === null) {
            return [];
        }

        return $this->availability()->slots($day, $this->guests, $this->holdOwner);
    }

    /**
     * @return Collection<int, Hall>
     */
    #[Computed]
    public function halls(): Collection
    {
        return Hall::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->with(['tables' => fn ($query) => $query->where('is_active', true)->orderBy('id')])
            ->get();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function tableStates(): array
    {
        $start = $this->startsAt();

        if ($start === null) {
            return [];
        }

        return $this->availability()->tableStates($start, $this->guests, $this->holdOwner);
    }

    #[Computed]
    public function table(): ?DiningTable
    {
        if ($this->tableId === null) {
            return null;
        }

        return DiningTable::with('hall')->find($this->tableId);
    }

    #[Computed]
    public function doneReservation(): ?Reservation
    {
        if ($this->doneToken === null) {
            return null;
        }

        return Reservation::findByToken($this->doneToken);
    }

    public function startsAt(): ?CarbonImmutable
    {
        if ($this->date === null || $this->time === null) {
            return null;
        }

        return LocalTime::parse($this->date, $this->time);
    }

    /**
     * Плата за бронювання в копійках; 0 — бронювання безкоштовне (частина 2, п. 3.6).
     */
    public function fee(): int
    {
        return BookingSettings::fee();
    }

    /**
     * Попереднє замовлення приймається не пізніше ніж за заданий час до візиту (частина 2, п. 3.1).
     */
    public function preorderAvailable(): bool
    {
        $start = $this->startsAt();

        return $start !== null
            && now()->diffInMinutes($start, false) >= BookingSettings::preorderMinLead()
            && $this->preorderMenu->isNotEmpty();
    }

    /**
     * Скільки гість заплатить одразу: плата за бронювання плюс страви.
     */
    public function totalToPay(): int
    {
        $total = $this->fee();

        if ($this->wantsPreorder) {
            $total += $this->cartTotal();
        }

        return $total;
    }

    public function maxGuests(): int
    {
        return $this->availability()->maxGuests();
    }

    public function canGoToPreviousMonth(): bool
    {
        return $this->month > LocalTime::now()->format('Y-m');
    }

    public function canGoToNextMonth(): bool
    {
        return $this->month < LocalTime::now()->addDays(BookingSettings::horizonDays())->format('Y-m');
    }

    /**
     * «+380 67 123 45 67», «0671234567», «380671234567» → «+380671234567»; інше — null.
     */
    public static function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '38'.$digits;
        }

        if (preg_match('/^380\d{9}$/', $digits) !== 1) {
            return null;
        }

        return '+'.$digits;
    }

    public function render(): View
    {
        return view('livewire.booking-wizard', [
            'durationLabel' => self::humanMinutes(BookingSettings::duration()),
            'feeRefundLabel' => self::humanMinutes(BookingSettings::feeRefundUntil()),
            'preorderLeadLabel' => self::humanMinutes(BookingSettings::preorderMinLead()),
            'preorderCancelLabel' => self::humanMinutes(BookingSettings::preorderFreeCancel()),
        ]);
    }

    /**
     * 360 → «6 годин», 90 → «1 година 30 хвилин» мовою сторінки.
     */
    public static function humanMinutes(int $minutes): string
    {
        return CarbonInterval::minutes($minutes)->cascade()->locale(app()->getLocale())->forHumans();
    }

    // Один екземпляр на запит: сервіс запам'ятовує години роботи, особливі дні й столики,
    // а календар питає його про кожен день місяця
    private function availability(): AvailabilityService
    {
        return $this->availability ??= app(AvailabilityService::class);
    }

    private function day(): ?CarbonImmutable
    {
        if ($this->date === null) {
            return null;
        }

        return LocalTime::parse($this->date);
    }

    private function changeGuests(int $guests): void
    {
        $this->guests = max(1, min($guests, $this->maxGuests()));
        unset($this->timeSlots);

        // Для іншої кількості гостей обраний час може виявитися зайнятим
        if ($this->time !== null && ! $this->slotIsAvailable()) {
            $this->time = null;
        }

        $this->dropTable();
    }

    private function changeMonth(int $delta): void
    {
        $current = LocalTime::parse($this->month.'-01') ?? LocalTime::now()->startOfMonth();
        $target = $current->addMonthsNoOverflow($delta)->format('Y-m');
        $min = LocalTime::now()->format('Y-m');
        $max = LocalTime::now()->addDays(BookingSettings::horizonDays())->format('Y-m');

        if ($target >= $min && $target <= $max) {
            $this->month = $target;
        }
    }

    private function slotIsAvailable(): bool
    {
        foreach ($this->timeSlots as $slot) {
            if ($slot['time'] === $this->time) {
                return $slot['available'];
            }
        }

        return false;
    }

    private function holdIsValid(): bool
    {
        if ($this->tableId === null) {
            return false;
        }

        $hold = app(TableHold::class)->of($this->holdOwner);

        return $hold !== null && $hold['table_id'] === $this->tableId;
    }

    private function dropTable(): void
    {
        if ($this->tableId !== null) {
            app(TableHold::class)->release($this->holdOwner);
        }

        $this->tableId = null;
        $this->holdExpiresAt = null;
        unset($this->tableStates, $this->table);
    }

    /**
     * Зал, у якому є вільний столик; якщо таких немає — перший.
     */
    private function defaultHallId(): ?int
    {
        foreach ($this->halls as $hall) {
            foreach ($hall->tables as $table) {
                if (($this->tableStates[$table->id] ?? null) === AvailabilityService::STATE_FREE) {
                    return $hall->id;
                }
            }
        }

        return $this->halls->first()?->id;
    }

    /**
     * Стан із сесії міг застаріти (гість повернувся за годину): усе, що вже не чинне, скидається.
     */
    private function restoreState(): void
    {
        $now = LocalTime::now();

        if ($this->month < $now->format('Y-m') || LocalTime::parse($this->month.'-01') === null) {
            $this->month = $now->format('Y-m');
        }

        $day = $this->day();

        if ($day !== null && ! $this->availability()->isBookableDate($day)) {
            $this->date = null;
            $this->time = null;
        }

        if ($this->time !== null && ! $this->slotIsAvailable()) {
            $this->time = null;
        }

        if ($this->tableId !== null && ! $this->holdIsValid()) {
            $this->dropTable();
        }

        if ($this->time === null) {
            $this->step = 1;
        } elseif ($this->tableId === null) {
            $this->step = min($this->step, 2);
        }

        if ($this->step === 2 && $this->hallId === null) {
            $this->hallId = $this->defaultHallId();
        }

        // Кошик із сесії міг застаріти: лишаються тільки страви, які ще є в меню
        $this->cart = $this->cartItems();

        if (! $this->preorderAvailable()) {
            $this->wantsPreorder = false;
        }
    }

    private function validateContacts(): void
    {
        $this->validate(
            [
                'name' => ['required', 'string', 'min:2', 'max:80'],
                'phone' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                    if (self::normalizePhone((string) $value) === null) {
                        $fail(__('booking.contacts.phone_invalid'));
                    }
                }],
                'email' => ['required', 'email', 'max:255'],
                'comment' => ['nullable', 'string', 'max:500'],
                'consent' => ['accepted'],
            ],
            // Власні повідомлення: українського перекладу стандартних правил валідації в проєкті немає
            [
                'name.*' => __('booking.contacts.name_invalid'),
                'phone.required' => __('booking.contacts.phone_invalid'),
                'email.*' => __('booking.contacts.email_invalid'),
                'comment.*' => __('booking.contacts.comment_long'),
                'consent.accepted' => __('booking.contacts.consent_required'),
            ],
        );
    }
}
