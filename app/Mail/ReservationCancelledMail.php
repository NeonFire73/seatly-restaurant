<?php

namespace App\Mail;

use App\Models\Reservation;
use App\Support\Localization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('booking.mail.cancelled.subject', ['code' => $this->reservation->code]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.reservation-cancelled', with: [
            'bookingUrl' => Localization::route('booking', [], $this->reservation->locale),
        ]);
    }
}
