<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Actions\Reservations\CreateReservation as CreateReservationAction;
use App\Actions\Reservations\ReservationData;
use App\Enums\ReservationSource;
use App\Exceptions\Booking\BookingException;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Resources\Reservations\Schemas\ReservationForm;
use App\Support\Localization;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateReservation extends CreateRecord
{
    protected static string $resource = ReservationResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * Бронювання з адмінки створюється тією самою дією, що й на сайті (частина 2, п. 7.4).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $start = ReservationForm::moment($data['date'], $data['time']);

        try {
            if ($start === null) {
                throw new BookingException(__('booking.errors.slot'));
            }

            return app(CreateReservationAction::class)->handle(new ReservationData(
                tableId: (int) $data['table_id'],
                startsAt: $start,
                guests: (int) $data['guests_count'],
                name: $data['guest_name'],
                phone: $data['guest_phone'],
                email: $data['guest_email'] ?? null,
                comment: $data['comment'] ?? null,
                // Листи гостю, який бронював телефоном, ідуть основною мовою закладу
                locale: Localization::main(),
                source: ReservationSource::from($data['source']),
                createdBy: auth()->id(),
            ));
        } catch (BookingException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            // Зупиняє створення без сторінки помилки: форма лишається відкритою з уведеними даними
            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return ReservationResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
