@php
    use App\Support\LocalTime;
    use App\Support\Money;
@endphp

<x-mail::message>
# {{ __('booking.mail.preorder.title') }}

{{ __('booking.mail.greeting', ['name' => $reservation->guest_name]) }}

{{ __('booking.mail.preorder.intro', [
    'code' => $reservation->code,
    'date' => LocalTime::dateLabel($reservation->starts_at),
    'time' => LocalTime::timeLabel($reservation->starts_at),
]) }}

<x-mail::panel>
**{{ __('booking.mail.paid.total') }}:** {{ Money::format($payment->total()) }}

[{{ __('booking.mail.paid.receipt') }}]({{ $receiptUrl }})
</x-mail::panel>

<x-mail::button :url="$url">
{{ __('booking.mail.confirmed.button') }}
</x-mail::button>

{{ __('booking.mail.signature') }}<br>
{{ config('app.name') }}
</x-mail::message>
