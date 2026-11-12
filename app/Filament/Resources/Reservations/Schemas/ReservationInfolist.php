<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Models\Reservation;
use App\Support\Localization;
use App\Support\LocalTime;
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
}
