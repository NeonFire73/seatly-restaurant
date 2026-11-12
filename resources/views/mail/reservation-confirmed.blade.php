@php
    use App\Support\LocalTime;
@endphp

<x-mail::message>
# {{ __('booking.mail.confirmed.title') }}

{{ __('booking.mail.greeting', ['name' => $reservation->guest_name]) }}

{{ __('booking.mail.confirmed.intro') }}

<x-mail::panel>
**{{ __('booking.summary.code') }}:** {{ $reservation->code }}<br>
**{{ __('booking.summary.date') }}:** {{ LocalTime::dateLabel($reservation->starts_at) }}<br>
**{{ __('booking.summary.time') }}:** {{ LocalTime::timeLabel($reservation->starts_at) }}–{{ LocalTime::timeLabel($reservation->ends_at) }}<br>
**{{ __('booking.summary.table') }}:** №{{ $reservation->table->number }}, {{ $reservation->table->hall->name }}<br>
**{{ __('booking.summary.guests') }}:** {{ $reservation->guests_count }}
</x-mail::panel>

<x-mail::button :url="$url">
{{ __('booking.mail.confirmed.button') }}
</x-mail::button>

{{ __('booking.mail.confirmed.outro') }}

{{ __('booking.mail.signature') }}<br>
{{ config('app.name') }}
</x-mail::message>
