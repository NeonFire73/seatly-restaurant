<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Enums\ReservationSource;
use App\Livewire\BookingWizard;
use App\Models\DiningTable;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingSettings;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Коли й де')
                    ->columns(2)
                    ->schema(self::slotFields()),
                Section::make('Гість')
                    ->columns(2)
                    ->schema([
                        ...self::guestFields(),
                        Select::make('source')
                            ->label('Звідки бронювання')
                            ->options([
                                ReservationSource::Phone->value => 'Гість подзвонив',
                                ReservationSource::Admin->value => 'Гість у закладі',
                            ])
                            ->default(ReservationSource::Phone->value)
                            ->selectablePlaceholder(false)
                            ->required(),
                    ]),
            ]);
    }

    /**
     * Дата, час, гості й столик. Список столиків залежить від перших трьох полів:
     * показуються лише вільні. Ті самі поля використовує дія «Перенести».
     *
     * @param  int|null  $ignoreReservationId  бронювання, яке переносять: воно не заважає власному столику
     * @return list<DatePicker|Select|TextInput>
     */
    public static function slotFields(?int $ignoreReservationId = null): array
    {
        $resetTable = fn (Set $set) => $set('table_id', null);

        return [
            DatePicker::make('date')
                ->label('Дата')
                ->native(false)
                ->displayFormat('d.m.Y')
                ->minDate(fn () => LocalTime::now()->startOfDay())
                ->default(fn () => LocalTime::now()->format('Y-m-d'))
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('time', null);
                    $set('table_id', null);
                }),
            Select::make('time')
                ->label('Час')
                ->options(fn (Get $get) => self::timeOptions($get('date')))
                ->helperText('Персонал може бронювати на будь-який час у години роботи.')
                ->required()
                ->live()
                ->afterStateUpdated($resetTable),
            TextInput::make('guests_count')
                ->label('Гостей')
                ->integer()
                ->minValue(1)
                ->maxValue(30)
                ->default(2)
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated($resetTable),
            Select::make('table_id')
                ->label('Столик')
                ->options(fn (Get $get) => self::tableOptions($get('date'), $get('time'), $get('guests_count'), $ignoreReservationId))
                ->helperText('У списку лише вільні столики, що підходять за кількістю гостей.')
                ->required(),
        ];
    }

    /**
     * @return list<TextInput|Textarea>
     */
    public static function guestFields(): array
    {
        return [
            TextInput::make('guest_name')
                ->label("Ім'я")
                ->required()
                ->minLength(2)
                ->maxLength(80),
            TextInput::make('guest_phone')
                ->label('Телефон')
                ->tel()
                ->placeholder('+380 67 123 45 67')
                ->required()
                ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                    if (BookingWizard::normalizePhone((string) $value) === null) {
                        $fail('Вкажіть український номер у форматі +380 XX XXX XX XX.');
                    }
                })
                ->dehydrateStateUsing(fn (?string $state) => BookingWizard::normalizePhone((string) $state) ?? $state),
            TextInput::make('guest_email')
                ->label('E-mail')
                ->helperText('Якщо вказати, гість отримає лист із підтвердженням.')
                ->email()
                ->maxLength(255),
            Textarea::make('comment')
                ->label('Коментар')
                ->maxLength(500)
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    /**
     * Час із кроком 15 хвилин у години роботи обраного дня; на сьогодні — лише той, що ще не минув.
     *
     * @return array<string, string>
     */
    public static function timeOptions(?string $date): array
    {
        $day = self::day($date);

        if ($day === null) {
            return [];
        }

        $window = app(AvailabilityService::class)->dayWindow($day);

        if ($window === null) {
            return [];
        }

        $earliest = LocalTime::now()->subMinutes(BookingSettings::slotStep());
        $options = [];

        for ($moment = $window[0]; $moment->lt($window[1]); $moment = $moment->addMinutes(15)) {
            if ($moment->gte($earliest)) {
                $options[$moment->format('H:i')] = $moment->format('H:i');
            }
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public static function tableOptions(?string $date, ?string $time, mixed $guests, ?int $ignoreReservationId = null): array
    {
        $start = self::moment($date, $time);
        $guests = (int) $guests;

        if ($start === null || $guests < 1) {
            return [];
        }

        $states = app(AvailabilityService::class)->tableStates($start, $guests, ignoreReservationId: $ignoreReservationId);
        $free = array_keys($states, AvailabilityService::STATE_FREE, true);

        return DiningTable::query()
            ->with('hall')
            ->whereKey($free)
            ->orderBy('seats')
            ->get()
            ->mapWithKeys(fn (DiningTable $table) => [$table->id => self::tableLabel($table)])
            ->all();
    }

    public static function tableLabel(DiningTable $table): string
    {
        return "№{$table->number} · {$table->hall->name} · {$table->min_guests}–{$table->seats} гостей";
    }

    /**
     * Дата й час із форми (місцеві) → момент. Час після півночі належить робочому дню, що почався напередодні,
     * тож для закладу, відкритого до 02:00, «00:30» означає наступну календарну добу.
     */
    public static function moment(?string $date, ?string $time): ?CarbonImmutable
    {
        $day = self::day($date);

        if ($day === null || $time === null) {
            return null;
        }

        $moment = LocalTime::parse($day->format('Y-m-d'), substr($time, 0, 5));
        $window = app(AvailabilityService::class)->dayWindow($day);

        if ($moment !== null && $window !== null && $moment->lt($window[0])) {
            $moment = $moment->addDay();
        }

        return $moment;
    }

    private static function day(?string $date): ?CarbonImmutable
    {
        if ($date === null || $date === '') {
            return null;
        }

        // DatePicker віддає «2026-11-12» або «2026-11-12 00:00:00»
        return LocalTime::parse(substr($date, 0, 10));
    }
}
