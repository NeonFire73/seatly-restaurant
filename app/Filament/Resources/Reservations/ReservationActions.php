<?php

namespace App\Filament\Resources\Reservations;

use App\Actions\Reservations\CancelReservation;
use App\Actions\Reservations\CompleteReservation;
use App\Actions\Reservations\MarkReservationNoShow;
use App\Actions\Reservations\MoveReservation;
use App\Actions\Reservations\RestoreReservation;
use App\Actions\Reservations\SeatReservation;
use App\Enums\ReservationStatus;
use App\Exceptions\Booking\BookingException;
use App\Filament\Resources\Reservations\Schemas\ReservationForm;
use App\Models\Reservation;
use App\Models\User;
use App\Support\LocalTime;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Дії над бронюванням (частина 2, п. 7.4) — спільні для таблиці й картки.
 * Самі зміни виконують класи з app/Actions; тут лише кнопки, права й повідомлення.
 */
class ReservationActions
{
    public static function seat(): Action
    {
        return Action::make('seat')
            ->label('Посадити')
            ->icon(Heroicon::OutlinedUserGroup)
            ->color('primary')
            ->visible(fn (Reservation $record) => self::allowed('seat', $record, ReservationStatus::Seated))
            ->action(fn (Reservation $record) => self::run(
                fn () => app(SeatReservation::class)->handle($record, auth()->user()),
                'Гостей посаджено за столик №'.$record->table->number,
            ));
    }

    public static function complete(): Action
    {
        return Action::make('complete')
            ->label('Завершити візит')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Столик стане вільним для наступних гостей.')
            ->visible(fn (Reservation $record) => self::allowed('complete', $record, ReservationStatus::Completed))
            ->action(fn (Reservation $record) => self::run(
                fn () => app(CompleteReservation::class)->handle($record),
                'Візит завершено',
            ));
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Скасувати')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->modalHeading('Скасувати бронювання?')
            ->modalDescription('Гість отримає лист про скасування, якщо вказано e-mail.')
            ->modalSubmitActionLabel('Скасувати бронювання')
            ->schema([
                Textarea::make('reason')
                    ->label('Причина')
                    ->maxLength(255)
                    ->rows(2),
            ])
            ->visible(fn (Reservation $record) => self::allowed('cancel', $record, ReservationStatus::Cancelled))
            ->action(function (Reservation $record, array $data): void {
                /** @var User $user */
                $user = auth()->user();

                self::run(
                    fn () => app(CancelReservation::class)->handle($record, $data['reason'] ?? null, $user),
                    'Бронювання скасовано',
                );
            });
    }

    public static function noShow(): Action
    {
        return Action::make('noShow')
            ->label('Не прийшов')
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('Столик звільниться. Помилкову позначку можна буде зняти.')
            // До початку візиту гість ще не міг «не прийти»
            ->visible(fn (Reservation $record) => self::allowed('markNoShow', $record, ReservationStatus::NoShow) && $record->starts_at->isPast())
            ->action(fn (Reservation $record) => self::run(
                fn () => app(MarkReservationNoShow::class)->handle($record),
                'Позначено «не прийшов»',
            ));
    }

    public static function restore(): Action
    {
        return Action::make('restoreStatus')
            ->label('Повернути в «Підтверджено»')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Reservation $record) => $record->status === ReservationStatus::NoShow && Gate::allows('restoreStatus', $record))
            ->action(fn (Reservation $record) => self::run(
                fn () => app(RestoreReservation::class)->handle($record),
                'Бронювання знову підтверджене',
            ));
    }

    public static function move(): Action
    {
        return Action::make('move')
            ->label('Перенести')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->modalHeading('Перенести бронювання')
            ->modalSubmitActionLabel('Перенести')
            ->schema(fn (Reservation $record) => ReservationForm::slotFields($record->id))
            ->fillForm(function (Reservation $record): array {
                $start = LocalTime::of($record->starts_at);

                return [
                    'date' => $start->format('Y-m-d'),
                    'time' => $start->format('H:i'),
                    'guests_count' => $record->guests_count,
                    'table_id' => $record->table_id,
                ];
            })
            ->visible(fn (Reservation $record) => in_array($record->status, [ReservationStatus::PendingPayment, ReservationStatus::Confirmed], true)
                && Gate::allows('update', $record))
            ->action(function (Reservation $record, array $data): void {
                $start = ReservationForm::moment($data['date'], $data['time']);

                if ($start === null) {
                    return;
                }

                self::run(
                    fn () => app(MoveReservation::class)->handle($record, (int) $data['table_id'], $start, (int) $data['guests_count']),
                    'Бронювання перенесено',
                );
            });
    }

    public static function editGuest(): Action
    {
        return Action::make('editGuest')
            ->label('Змінити дані гостя')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->modalHeading('Дані гостя')
            ->schema(ReservationForm::guestFields())
            ->fillForm(fn (Reservation $record) => $record->only(['guest_name', 'guest_phone', 'guest_email', 'comment']))
            ->visible(fn (Reservation $record) => Gate::allows('update', $record))
            ->action(function (Reservation $record, array $data): void {
                $record->update($data);

                Notification::make()->title('Дані гостя збережено')->success()->send();
            });
    }

    private static function allowed(string $ability, Reservation $record, ReservationStatus $next): bool
    {
        return $record->status->canTransitionTo($next) && Gate::allows($ability, $record);
    }

    /**
     * Доменна помилка (столик зайнятий, заборонений перехід) показується сповіщенням, а не сторінкою 500.
     */
    private static function run(Closure $callback, string $success): void
    {
        try {
            $callback();
        } catch (BookingException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($success)->success()->send();
    }
}
