<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\Reservation;
use App\Support\Ics;
use App\Support\Localization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Лист гостю: бронювання підтверджено. Надсилається мовою бронювання — її задає ->locale() під час відправлення.
 */
class ReservationConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Payment|null  $payment  оплата, яка підтвердила бронювання: тоді лист містить і квитанцію (частина 2, п. 3.7)
     */
    public function __construct(public Reservation $reservation, public ?Payment $payment = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('booking.mail.confirmed.subject', ['code' => $this->reservation->code]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.reservation-confirmed', with: [
            'url' => Localization::route('reservation.show', ['token' => $this->reservation->token], $this->reservation->locale),
            'receiptUrl' => $this->receiptUrl(),
        ]);
    }

    private function receiptUrl(): ?string
    {
        if ($this->payment === null) {
            return null;
        }

        return Localization::route('payment.receipt', ['payment' => $this->payment->id], $this->reservation->locale);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => Ics::forReservation($this->reservation), 'seatly-'.$this->reservation->code.'.ics')
                ->withMime('text/calendar'),
        ];
    }
}
