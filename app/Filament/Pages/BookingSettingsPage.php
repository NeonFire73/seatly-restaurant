<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\Booking\BookingSettings;
use App\Services\Payments\TipSettings;
use App\Services\SiteContent;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use UnitEnum;

/**
 * Сторінка «Налаштування» (частина 2, пп. 3.6 і 7.9): параметри бронювання, платне бронювання,
 * правила попереднього замовлення й чайові. Значення лежать у settings під ключами `booking` і `tips`
 * та перекривають типові з config/seatly.php. Години роботи — окрема сторінка WorkingHoursPage.
 *
 * @property-read Schema $form
 */
class BookingSettingsPage extends Page
{
    protected string $view = 'filament.pages.booking-settings';

    protected static ?string $slug = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Керування';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Налаштування';

    // Параметри бронювання з п. 3.1: ключ => [підпис, одиниця, найменше, найбільше]
    private const BOOKING_FIELDS = [
        'duration' => ['Тривалість бронювання', 'хв', 30, 480],
        'slot_step' => ['Крок слотів', 'хв', 5, 120],
        'buffer' => ['Перерва між бронюваннями одного столика', 'хв', 0, 120],
        'min_lead' => ['Бронювати не пізніше ніж за', 'хв до візиту', 0, 1440],
        'horizon_days' => ['Бронювати наперед не далі ніж на', 'днів', 1, 365],
        'last_slot_before_close' => ['Останній слот перед закриттям — за', 'хв', 0, 480],
        'hold' => ['Утримувати обраний столик у майстрі бронювання', 'хв', 1, 60],
        'no_show_after' => ['Позначати «не прийшов» після початку через', 'хв', 5, 240],
        'max_guests' => ['Найбільша компанія для онлайн-бронювання', 'гостей', 1, 50],
    ];

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows('manage-booking-payments');
    }

    public function mount(): void
    {
        $this->form->fill([
            'fee_enabled' => BookingSettings::get('fee_enabled') === 1,
            'fee_amount' => Money::toInput(BookingSettings::get('fee_amount')),
            'fee_refund_hours' => self::toHours(BookingSettings::feeRefundUntil()),
            'payment_hold' => BookingSettings::paymentHold(),
            'preorder_min_lead_hours' => self::toHours(BookingSettings::preorderMinLead()),
            'preorder_free_cancel_hours' => self::toHours(BookingSettings::preorderFreeCancel()),
            ...self::bookingState(),
            'tip_percents' => array_map(strval(...), TipSettings::percents()),
            'tip_default_percent' => TipSettings::defaultPercent(),
            'tip_max_percent' => TipSettings::maxPercent(),
            'tip_show_waiter_name' => TipSettings::showWaiterName(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(2)
            ->components([
                Section::make('Параметри бронювання')
                    ->description('Діють на нові бронювання; уже створені не змінюються.')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema(self::bookingFields()),
                Section::make('Платне бронювання')
                    ->description('Плата за сам факт бронювання на сайті. У рахунок за страви не зараховується; бронювання, створені в адмінці, безкоштовні.')
                    ->columnSpan(1)
                    ->schema([
                        Toggle::make('fee_enabled')
                            ->label('Брати плату за бронювання')
                            ->live(),
                        TextInput::make('fee_amount')
                            ->label('Плата за одне бронювання')
                            ->suffix('грн')
                            ->helperText('Діє лише на нові бронювання: уже створені зберігають свою суму.')
                            ->required(fn (Get $get) => (bool) $get('fee_enabled'))
                            ->rule(fn (Get $get) => self::feeRule((bool) $get('fee_enabled'))),
                        TextInput::make('fee_refund_hours')
                            ->label('Повертати плату, якщо гість скасував не пізніше ніж за')
                            ->suffix('год до візиту')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(720),
                    ]),
                Section::make('Попереднє замовлення й оплата')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('preorder_min_lead_hours')
                            ->label('Приймати попереднє замовлення не пізніше ніж за')
                            ->suffix('год до візиту')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(720),
                        TextInput::make('preorder_free_cancel_hours')
                            ->label('Повертати кошти за страви, якщо гість скасував не пізніше ніж за')
                            ->suffix('год до візиту')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(720),
                        TextInput::make('payment_hold')
                            ->label('Тримати столик за неоплаченим бронюванням')
                            ->suffix('хв')
                            ->integer()
                            ->required()
                            ->minValue(5)
                            ->maxValue(120),
                    ]),
                Section::make('Чайові')
                    ->description('Що бачить гість, коли оплачує рахунок за QR-кодом столика.')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TagsInput::make('tip_percents')
                            ->label('Варіанти у відсотках')
                            ->helperText('Наприклад: 0, 5, 10, 15. Після кожного числа натисніть Enter.')
                            ->placeholder('Додати відсоток')
                            ->required()
                            ->nestedRecursiveRules(['integer', 'min:0', 'max:100'])
                            ->columnSpanFull(),
                        TextInput::make('tip_default_percent')
                            ->label('Вибрано одразу')
                            ->suffix('%')
                            ->integer()
                            ->required()
                            ->rule(fn (Get $get) => self::defaultTipRule((array) $get('tip_percents'))),
                        TextInput::make('tip_max_percent')
                            ->label('Найбільші чайові «своєю сумою»')
                            ->suffix('% від суми платежу')
                            ->integer()
                            ->required()
                            ->minValue(1)
                            ->maxValue(100),
                        Toggle::make('tip_show_waiter_name')
                            ->label('Показувати гостеві ім\'я офіціанта')
                            ->inline(false),
                    ]),
            ]);
    }

    /**
     * Зберігає через підтвердження: власник бачить нову суму плати ще раз, перш ніж вона почне діяти (частина 2, п. 3.6).
     */
    public function saveAction(): Action
    {
        return Action::make('save')
            ->label('Зберегти')
            ->requiresConfirmation()
            ->modalHeading('Зберегти налаштування?')
            ->modalDescription(fn () => $this->confirmationText())
            ->modalSubmitActionLabel('Зберегти')
            ->action(fn () => $this->save());
    }

    public function save(): void
    {
        // Сторінку менеджерові не видно, але запит можна надіслати й напряму — право перевіряється на сервері
        Gate::authorize('manage-booking-payments');

        $state = $this->form->getState();
        $enabled = (bool) $state['fee_enabled'];
        $fee = 0;

        if (filled($state['fee_amount'])) {
            $fee = Money::toKopecks($state['fee_amount']);
        }

        $general = [];

        foreach (array_keys(self::BOOKING_FIELDS) as $key) {
            $general[$key] = (int) $state[$key];
        }

        $percents = array_values(array_unique(array_map(intval(...), $state['tip_percents'])));
        sort($percents);

        Setting::put('tips', [
            ...(array) SiteContent::setting('tips', []),
            'percents' => $percents,
            'default_percent' => (int) $state['tip_default_percent'],
            'max_percent' => (int) $state['tip_max_percent'],
            'show_waiter_name' => (bool) $state['tip_show_waiter_name'],
        ]);

        Setting::put('booking', [
            ...(array) SiteContent::setting('booking', []),
            ...$general,
            'fee_enabled' => $enabled,
            'fee_amount' => $fee,
            'fee_refund_until' => self::toMinutes($state['fee_refund_hours']),
            'payment_hold' => (int) $state['payment_hold'],
            'preorder_min_lead' => self::toMinutes($state['preorder_min_lead_hours']),
            'preorder_free_cancel' => self::toMinutes($state['preorder_free_cancel_hours']),
        ]);

        Notification::make()
            ->title('Налаштування збережено')
            ->success()
            ->send();
    }

    /**
     * @return array<string, int>
     */
    private static function bookingState(): array
    {
        $state = [];

        foreach (array_keys(self::BOOKING_FIELDS) as $key) {
            $state[$key] = BookingSettings::get($key);
        }

        return $state;
    }

    /**
     * @return list<TextInput>
     */
    private static function bookingFields(): array
    {
        $fields = [];

        foreach (self::BOOKING_FIELDS as $key => [$label, $suffix, $min, $max]) {
            $fields[] = TextInput::make($key)
                ->label($label)
                ->suffix($suffix)
                ->integer()
                ->required()
                ->minValue($min)
                ->maxValue($max);
        }

        return $fields;
    }

    /**
     * Типовий відсоток має бути серед варіантів: інакше гість побачив би вибраною кнопку, якої немає.
     *
     * @param  array<int|string, mixed>  $percents
     */
    private static function defaultTipRule(array $percents): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($percents): void {
            if (! in_array((int) $value, array_map(intval(...), $percents), true)) {
                $fail('Оберіть один із варіантів, перелічених вище.');
            }
        };
    }

    private function confirmationText(): string
    {
        if (! ($this->data['fee_enabled'] ?? false)) {
            return 'Бронювання на сайті буде безкоштовним.';
        }

        try {
            $fee = Money::format(Money::toKopecks($this->data['fee_amount'] ?? null), 'uk');
        } catch (InvalidArgumentException) {
            return 'Перевірте суму плати за бронювання.';
        }

        return "Кожне нове бронювання на сайті коштуватиме гостеві {$fee} і підтверджуватиметься лише після оплати.";
    }

    /**
     * Сума — у гривнях із копійками; увімкнути платне бронювання з нульовою сумою не можна.
     */
    private static function feeRule(bool $enabled): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($enabled): void {
            if (blank($value)) {
                return;
            }

            try {
                $kopecks = Money::toKopecks($value);
            } catch (InvalidArgumentException) {
                $fail('Вкажіть суму числом, наприклад 200 або 199.50.');

                return;
            }

            if ($enabled && $kopecks === 0) {
                $fail('Щоб брати плату, вкажіть суму більшу за нуль.');
            }

            if ($kopecks > 100_000_00) {
                $fail('Сума завелика.');
            }
        };
    }

    private static function toHours(int $minutes): float|int
    {
        if ($minutes % 60 === 0) {
            return intdiv($minutes, 60);
        }

        return round($minutes / 60, 2);
    }

    private static function toMinutes(mixed $hours): int
    {
        return (int) round(((float) $hours) * 60);
    }
}
