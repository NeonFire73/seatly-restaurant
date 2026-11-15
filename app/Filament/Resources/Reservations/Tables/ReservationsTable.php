<?php

namespace App\Filament\Resources\Reservations\Tables;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Filament\Resources\Reservations\ReservationActions;
use App\Filament\Resources\Reservations\Schemas\ReservationInfolist;
use App\Filament\Support\Translations;
use App\Models\Hall;
use App\Models\Reservation;
use App\Support\LocalTime;
use App\Support\Money;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['table.hall', 'payments', 'preorder']))
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Дата й час')
                    ->dateTime('d.m.Y H:i', LocalTime::zone())
                    ->description(fn (Reservation $record) => 'до '.LocalTime::timeLabel($record->ends_at))
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Код')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('guest_name')
                    ->label('Гість')
                    ->weight('medium')
                    ->description(fn (Reservation $record) => $record->guest_phone)
                    ->searchable(['guest_name', 'guest_phone']),
                TextColumn::make('guests_count')
                    ->label('Гостей')
                    ->alignCenter(),
                TextColumn::make('table.number')
                    ->label('Столик')
                    ->prefix('№')
                    ->description(fn (Reservation $record) => $record->table->hall->name),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge(),
                TextColumn::make('fee_amount')
                    ->label('Плата')
                    ->formatStateUsing(function (int $state): string {
                        if ($state === 0) {
                            return '—';
                        }

                        return Money::format($state, 'uk');
                    })
                    ->description(fn (Reservation $record) => ReservationInfolist::feeStateLabel($record))
                    ->toggleable(),
                TextColumn::make('preorder.subtotal')
                    ->label('Попереднє замовлення')
                    ->formatStateUsing(fn (int $state) => Money::format($state, 'uk'))
                    ->description(fn (Reservation $record) => $record->preorder?->status->getLabel())
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('source')
                    ->label('Джерело')
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime('d.m.Y H:i', LocalTime::zone())
                    ->color('gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('date')
                    ->schema([
                        DatePicker::make('date')
                            ->label('Дата')
                            ->native(false)
                            ->displayFormat('d.m.Y'),
                    ])
                    ->query(function (Builder $query, array $data): void {
                        $day = null;

                        if (filled($data['date'] ?? null)) {
                            $day = LocalTime::parse(substr((string) $data['date'], 0, 10));
                        }

                        if ($day !== null) {
                            // Межі місцевої доби, переведені в UTC, у якому час лежить у базі
                            $query->whereBetween('starts_at', [$day->utc(), $day->endOfDay()->utc()]);
                        }
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (blank($data['date'] ?? null)) {
                            return null;
                        }

                        return 'Дата: '.LocalTime::parse(substr((string) $data['date'], 0, 10))?->format('d.m.Y');
                    }),
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(ReservationStatus::class)
                    ->multiple(),
                SelectFilter::make('hall')
                    ->label('Зал')
                    ->options(fn () => Translations::options(Hall::orderBy('sort')))
                    ->query(function (Builder $query, array $data): void {
                        if (filled($data['value'] ?? null)) {
                            $query->whereHas('table', fn (Builder $table) => $table->where('hall_id', $data['value']));
                        }
                    }),
                SelectFilter::make('source')
                    ->label('Джерело')
                    ->options(ReservationSource::class),
            ])
            ->recordActions([
                ReservationActions::seat(),
                ViewAction::make(),
                ActionGroup::make([
                    ReservationActions::confirm(),
                    ReservationActions::complete(),
                    ReservationActions::move(),
                    ReservationActions::editGuest(),
                    ReservationActions::noShow(),
                    ReservationActions::restore(),
                    ReservationActions::cancel(),
                ]),
            ]);
    }
}
