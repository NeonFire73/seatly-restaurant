<?php

namespace App\Filament\Pages;

use App\Models\SpecialDay;
use App\Models\User;
use App\Models\WorkingHour;
use App\Support\LocalTime;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Сторінка «Години роботи» (частина 2, п. 7.9): тижневий графік і особливі дні.
 * Єдина частина налаштувань, доступна менеджерові; решта — на сторінці «Налаштування» лише для власника.
 *
 * @property-read Schema $form
 */
class WorkingHoursPage extends Page
{
    private const WEEKDAYS = [
        1 => 'Понеділок',
        2 => 'Вівторок',
        3 => 'Середа',
        4 => 'Четвер',
        5 => 'П\'ятниця',
        6 => 'Субота',
        7 => 'Неділя',
    ];

    protected string $view = 'filament.pages.working-hours';

    protected static ?string $slug = 'working-hours';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Керування';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Години роботи';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isManagement();
    }

    public function mount(): void
    {
        $stored = WorkingHour::all()->keyBy('weekday');
        $hours = [];

        foreach (array_keys(self::WEEKDAYS) as $weekday) {
            $row = $stored->get($weekday);

            // Ключі рядкові (d1…d7): із числовими Filament під час заповнення форми перенумеровує масив
            $hours["d{$weekday}"] = [
                // День, якого в базі ще немає, для бронювання закритий — так його показує й форма
                'is_closed' => $row === null || $row->is_closed,
                'opens_at' => self::time($row?->opens_at),
                'closes_at' => self::time($row?->closes_at),
            ];
        }

        $specialDays = SpecialDay::query()
            ->where('date', '>=', LocalTime::now()->format('Y-m-d'))
            ->orderBy('date')
            ->get()
            ->map(fn (SpecialDay $day) => [
                'date' => $day->date->format('Y-m-d'),
                'is_closed' => $day->is_closed,
                'opens_at' => self::time($day->opens_at),
                'closes_at' => self::time($day->closes_at),
                'note' => $day->note,
            ])
            ->all();

        $this->form->fill(['hours' => $hours, 'special_days' => $specialDays]);
    }

    public function form(Schema $schema): Schema
    {
        $days = [];

        foreach (self::WEEKDAYS as $weekday => $label) {
            $days[] = Fieldset::make($label)
                ->columns(3)
                ->schema([
                    Toggle::make("hours.d{$weekday}.is_closed")
                        ->label('Вихідний')
                        ->inline(false)
                        ->live(),
                    ...self::timeFields("hours.d{$weekday}"),
                ]);
        }

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Тижневий графік')
                    ->description('Від цих годин залежать слоти бронювання на сайті. Закриття «00:00» або раніше за відкриття означає наступну добу.')
                    ->schema($days),
                Section::make('Особливі дні')
                    ->description('Свята й санітарні дні: заклад зачинений або працює за іншим графіком. Минулі дні зі списку прибираються самі.')
                    ->schema([
                        Repeater::make('special_days')
                            ->label('Особливі дні')
                            ->hiddenLabel()
                            ->addActionLabel('Додати день')
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->columns(5)
                            ->schema([
                                DatePicker::make('date')
                                    ->label('Дата')
                                    ->required()
                                    ->distinct(),
                                Toggle::make('is_closed')
                                    ->label('Зачинено')
                                    ->inline(false)
                                    ->default(true)
                                    ->live(),
                                ...self::timeFields(null),
                                TextInput::make('note')
                                    ->label('Примітка')
                                    ->maxLength(255),
                            ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $state = $this->form->getState();

        DB::transaction(function () use ($state): void {
            foreach (array_keys(self::WEEKDAYS) as $weekday) {
                $row = $state['hours']["d{$weekday}"];

                WorkingHour::updateOrCreate(['weekday' => $weekday], [
                    'is_closed' => (bool) $row['is_closed'],
                    'opens_at' => $row['opens_at'] ?? null,
                    'closes_at' => $row['closes_at'] ?? null,
                ]);
            }

            // Майбутні особливі дні форма показує всі, тож зберігає їх цілим списком; минулі не чіпає
            $today = LocalTime::now()->format('Y-m-d');
            SpecialDay::where('date', '>=', $today)->delete();

            foreach ($state['special_days'] ?? [] as $day) {
                $closed = (bool) $day['is_closed'];
                $opensAt = null;
                $closesAt = null;

                if (! $closed) {
                    $opensAt = $day['opens_at'];
                    $closesAt = $day['closes_at'];
                }

                SpecialDay::updateOrCreate(['date' => substr((string) $day['date'], 0, 10)], [
                    'is_closed' => $closed,
                    'opens_at' => $opensAt,
                    'closes_at' => $closesAt,
                    'note' => $day['note'] ?? null,
                ]);
            }
        });

        Notification::make()
            ->title('Години роботи збережено')
            ->success()
            ->send();
    }

    /**
     * Поля «відчинено з — до»: обов'язкові й видимі лише для робочого дня.
     *
     * @param  string|null  $prefix  шлях до рядка графіка; null — поля всередині рядка повторювача
     * @return list<TimePicker>
     */
    private static function timeFields(?string $prefix): array
    {
        $path = function (string $field) use ($prefix): string {
            if ($prefix === null) {
                return $field;
            }

            return "{$prefix}.{$field}";
        };
        $isOpen = fn (Get $get): bool => ! $get($path('is_closed'));

        return [
            TimePicker::make($path('opens_at'))
                ->label('Відчиняється')
                ->seconds(false)
                ->visible($isOpen)
                ->required($isOpen),
            TimePicker::make($path('closes_at'))
                ->label('Зачиняється')
                ->seconds(false)
                ->visible($isOpen)
                ->required($isOpen),
        ];
    }

    private static function time(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return substr((string) $value, 0, 5);
    }
}
