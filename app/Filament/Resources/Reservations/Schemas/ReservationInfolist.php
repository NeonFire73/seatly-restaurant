<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Enums\OrderItemStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Support\Localization;
use App\Support\LocalTime;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReservationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Бронювання')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Код')
                            ->fontFamily('mono')
                            ->copyable(),
                        TextEntry::make('status')
                            ->label('Статус')
                            ->badge(),
                        TextEntry::make('starts_at')
                            ->label('Дата й час')
                            ->formatStateUsing(fn (Reservation $record) => LocalTime::of($record->starts_at)->format('d.m.Y H:i').'–'.LocalTime::timeLabel($record->ends_at)),
                        TextEntry::make('guests_count')
                            ->label('Гостей'),
                        TextEntry::make('table.number')
                            ->label('Столик')
                            ->formatStateUsing(fn (Reservation $record) => ReservationForm::tableLabel($record->table)),
                        TextEntry::make('source')
                            ->label('Джерело'),
                        TextEntry::make('creator.name')
                            ->label('Створив')
                            ->placeholder('гість на сайті'),
                        TextEntry::make('created_at')
                            ->label('Створено')
                            ->dateTime('d.m.Y H:i', LocalTime::zone()),
                        TextEntry::make('seated_at')
                            ->label('Посаджено')
                            ->dateTime('d.m.Y H:i', LocalTime::zone())
                            ->visible(fn (Reservation $record) => $record->seated_at !== null),
                        TextEntry::make('cancelled_at')
                            ->label('Скасовано')
                            ->dateTime('d.m.Y H:i', LocalTime::zone())
                            ->visible(fn (Reservation $record) => $record->cancelled_at !== null),
                        TextEntry::make('cancel_reason')
                            ->label('Причина скасування')
                            ->columnSpanFull()
                            ->visible(fn (Reservation $record) => filled($record->cancel_reason)),
                    ]),
                Section::make('Оплата й попереднє замовлення')
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn (Reservation $record) => $record->fee_amount > 0 || $record->preorder !== null)
                    ->schema([
                        TextEntry::make('fee_amount')
                            ->label('Плата за бронювання')
                            ->formatStateUsing(fn (int $state) => Money::format($state, 'uk'))
                            ->visible(fn (Reservation $record) => $record->fee_amount > 0),
                        TextEntry::make('fee_state')
                            ->label('Стан плати')
                            ->badge()
                            ->state(fn (Reservation $record) => self::feeStateLabel($record))
                            ->color(fn (Reservation $record) => self::feeStateColor($record))
                            ->visible(fn (Reservation $record) => $record->fee_amount > 0),
                        TextEntry::make('expires_at')
                            ->label('Оплатити до')
                            ->dateTime('d.m.Y H:i', LocalTime::zone())
                            ->visible(fn (Reservation $record) => $record->expires_at !== null),
                        TextEntry::make('preorder.number')
                            ->label('Попереднє замовлення')
                            ->fontFamily('mono')
                            ->url(fn (Reservation $record) => OrderResource::getUrl('view', ['record' => $record->preorder]))
                            ->visible(fn (Reservation $record) => $record->preorder !== null),
                        TextEntry::make('preorder.status')
                            ->label('Стан замовлення')
                            ->badge()
                            ->visible(fn (Reservation $record) => $record->preorder !== null),
                        TextEntry::make('preorder.subtotal')
                            ->label('Сума страв')
                            ->formatStateUsing(fn (int $state) => Money::format($state, 'uk'))
                            ->visible(fn (Reservation $record) => $record->preorder !== null),
                        TextEntry::make('preorder_items')
                            ->label('Страви')
                            ->state(fn (Reservation $record) => self::preorderLines($record))
                            ->listWithLineBreaks()
                            ->columnSpanFull()
                            ->visible(fn (Reservation $record) => $record->preorder !== null),
                    ]),
                Section::make('Гість')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('guest_name')
                            ->label("Ім'я"),
                        TextEntry::make('guest_phone')
                            ->label('Телефон')
                            ->url(fn (Reservation $record) => 'tel:'.$record->guest_phone),
                        TextEntry::make('guest_email')
                            ->label('E-mail')
                            ->placeholder('не вказано'),
                        TextEntry::make('locale')
                            ->label('Мова листів')
                            ->formatStateUsing(fn (string $state) => config("seatly.locales.{$state}", $state)),
                        TextEntry::make('comment')
                            ->label('Коментар')
                            ->placeholder('немає')
                            ->columnSpanFull(),
                        TextEntry::make('token')
                            ->label('Сторінка бронювання гостя')
                            ->formatStateUsing(fn () => 'Відкрити на сайті')
                            ->url(fn (Reservation $record) => Localization::route('reservation.show', ['token' => $record->token], $record->locale), shouldOpenInNewTab: true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Стан плати за бронювання словами (частина 2, п. 7.4); null — бронювання безкоштовне.
     */
    public static function feeStateLabel(Reservation $reservation): ?string
    {
        return match ($reservation->feeState()) {
            'paid' => 'сплачено',
            'refunded' => 'повернено',
            'pending' => 'очікує оплати',
            'unpaid' => 'не сплачено',
            default => null,
        };
    }

    private static function feeStateColor(Reservation $reservation): string
    {
        return match ($reservation->feeState()) {
            'paid' => 'success',
            'refunded' => 'info',
            'pending' => 'warning',
            default => 'gray',
        };
    }

    /**
     * @return list<string>
     */
    private static function preorderLines(Reservation $reservation): array
    {
        $lines = [];

        foreach ($reservation->preorder->items ?? [] as $item) {
            /** @var OrderItem $item */
            if ($item->status === OrderItemStatus::Cancelled) {
                continue;
            }

            $lines[] = $item->getTranslation('name', 'uk').' × '.$item->quantity.' — '.Money::format($item->total(), 'uk');
        }

        return $lines;
    }
}
