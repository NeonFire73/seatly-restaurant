<?php

namespace App\Mail;

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

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('booking.mail.confirmed.subject', ['code' => $this->reservation->code]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.reservation-confirmed', with: [
            'url' => Localization::route('reservation.show', ['token' => $this->reservation->token], $this->reservation->locale),
        ]);
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
