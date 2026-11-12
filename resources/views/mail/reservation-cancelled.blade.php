@php
    use App\Support\LocalTime;
@endphp

<x-mail::message>
# {{ __('booking.mail.cancelled.title') }}

{{ __('booking.mail.greeting', ['name' => $reservation->guest_name]) }}

{{ __('booking.mail.cancelled.intro', [
    'code' => $reservation->code,
    'date' => LocalTime::dateLabel($reservation->starts_at),
    'time' => LocalTime::timeLabel($reservation->starts_at),
]) }}

<x-mail::button :url="$bookingUrl">
{{ __('booking.mail.cancelled.button') }}
</x-mail::button>

{{ __('booking.mail.signature') }}<br>
{{ config('app.name') }}
</x-mail::message>
