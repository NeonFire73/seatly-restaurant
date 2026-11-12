<?php

namespace App\Livewire;

use App\Actions\Reservations\CancelReservation;
use App\Exceptions\Booking\BookingException;
use App\Livewire\Concerns\KeepsPageLocale;
use App\Models\Reservation;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Сторінка бронювання гостя (частина 1, п. 5.4). Попереднє замовлення й оплата додаються на етапі 7.
 *
 * @property-read Reservation|null $reservation
 */
class ReservationPage extends Component
{
    use KeepsPageLocale;

    #[Locked]
    public string $token = '';

    public string $notice = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        abort_if($this->reservation === null, 404);
    }

    #[Computed]
    public function reservation(): ?Reservation
    {
        $reservation = Reservation::findByToken($this->token);
        $reservation?->load('table.hall');

        return $reservation;
    }

    public function cancel(CancelReservation $cancelReservation): void
    {
        $reservation = $this->reservation;

        abort_if($reservation === null, 404);

        if (! $reservation->isCancellableByGuest()) {
            $this->notice = __('booking.page.cannot_cancel');

            return;
        }

        try {
            $cancelReservation->handle($reservation);
        } catch (BookingException) {
            $this->notice = __('booking.page.cannot_cancel');

            return;
        }

        unset($this->reservation);
        $this->notice = __('booking.page.cancelled');
    }

    public function render(): View
    {
        return view('livewire.reservation-page');
    }
}
