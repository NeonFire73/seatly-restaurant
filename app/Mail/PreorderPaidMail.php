<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\Reservation;
use App\Support\Localization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Лист гостю: попереднє замовлення, додане до вже підтвердженого бронювання, оплачено (частина 2, п. 3.7).
 */
class PreorderPaidMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation, public Payment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('booking.mail.preorder.subject', ['code' => $this->reservation->code]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.preorder-paid', with: [
            'url' => Localization::route('reservation.show', ['token' => $this->reservation->token], $this->reservation->locale),
            'receiptUrl' => Localization::route('payment.receipt', ['payment' => $this->payment->id], $this->reservation->locale),
        ]);
    }
}
