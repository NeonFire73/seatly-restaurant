@php
    use App\Support\LocalTime;
    use App\Support\Money;
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

@if ($payment)
<x-mail::panel>
@if ($payment->booking_fee_amount > 0)
**{{ __('booking.fee.label') }}:** {{ Money::format($payment->booking_fee_amount) }}<br>
@endif
@if ($payment->amount > 0)
**{{ __('booking.preorder.title') }}:** {{ Money::format($payment->amount) }}<br>
@endif
**{{ __('booking.mail.paid.total') }}:** {{ Money::format($payment->total()) }}

[{{ __('booking.mail.paid.receipt') }}]({{ $receiptUrl }})
</x-mail::panel>
@endif

<x-mail::button :url="$url">
{{ __('booking.mail.confirmed.button') }}
</x-mail::button>

{{ __('booking.mail.confirmed.outro') }}

{{ __('booking.mail.signature') }}<br>
{{ config('app.name') }}
</x-mail::message>
