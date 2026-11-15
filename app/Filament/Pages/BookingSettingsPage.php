<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\Booking\BookingSettings;
use App\Services\SiteContent;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
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
 * Сторінка «Налаштування» (частина 2, пп. 3.6 і 7.9): платне бронювання й правила попереднього замовлення.
 * Значення лежать у settings під ключем `booking` і перекривають типові з config/seatly.php.
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
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(2)
            ->components([
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

        Setting::put('booking', [
            ...(array) SiteContent::setting('booking', []),
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
