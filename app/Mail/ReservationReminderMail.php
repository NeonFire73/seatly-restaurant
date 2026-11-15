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

/**
 * Лист гостю: нагадування за кілька годин до візиту (частина 2, п. 3.7). Мову задає ->locale() під час відправлення.
 */
class ReservationReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('booking.mail.reminder.subject', ['code' => $this->reservation->code]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.reservation-reminder', with: [
            'url' => Localization::route('reservation.show', ['token' => $this->reservation->token], $this->reservation->locale),
            'preorder' => $this->reservation->preorder()->first(),
        ]);
    }
}
