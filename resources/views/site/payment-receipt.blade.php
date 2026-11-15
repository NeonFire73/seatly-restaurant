@php
    use App\Support\LocalTime;
    use App\Support\Money;

    $order = $payment->order;
    $reservation = $payment->reservation;
    // Номер у заголовку: замовлення, а для платежу лише за бронювання — код бронювання
    $number = $order->number ?? $reservation?->code;
@endphp

{{-- Квитанція для друку чи збереження в PDF засобами браузера. Окремий легкий макет: без навігації сайту --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('payment.receipt.title') }} {{ $number }} — {{ config('seatly.brand') }}</title>
        <meta name="robots" content="noindex, nofollow">
        @vite(['resources/css/app.css'])
    </head>
    <body class="bg-surface font-sans text-ink antialiased">
        <main class="mx-auto max-w-md px-4 py-8">
            <p class="text-xl font-bold">{{ config('app.name') }}</p>
            <h1 class="mt-4 text-2xl font-bold tracking-tight">{{ __('payment.receipt.title') }}</h1>

            <dl class="mt-4 space-y-1 text-sm">
                @if ($reservation)
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-soft">{{ __('payment.receipt.reservation') }}</dt>
                        <dd>{{ $reservation->code }}, {{ LocalTime::of($reservation->starts_at)->format('d.m.Y H:i') }}</dd>
                    </div>
                @endif
                @if ($order)
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-soft">{{ __('payment.receipt.order', ['number' => $order->number]) }}</dt>
                        <dd>{{ __('payment.receipt.table', ['number' => $order->table->number]) }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-4">
                    <dt class="text-ink-soft">{{ __('payment.receipt.paid_at') }}</dt>
                    <dd>{{ LocalTime::of($payment->paid_at ?? $payment->created_at)->format('d.m.Y H:i') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-ink-soft">{{ __('payment.receipt.payment') }}</dt>
                    <dd class="break-all text-right font-mono text-xs">{{ $payment->id }}</dd>
                </div>
            </dl>

            @if ($order)
                <h2 class="mt-6 text-sm font-semibold">{{ __('payment.receipt.items') }}</h2>
                <ul class="mt-2 divide-y divide-line border-y border-line text-sm">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-4 py-2">
                            <span class="min-w-0 break-words">{{ $item->name }} × {{ $item->quantity }}</span>
                            <span class="shrink-0">{{ Money::format($item->total()) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <dl class="mt-4 space-y-2 text-sm">
                @if ($payment->amount > 0)
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-soft">{{ __('payment.receipt.bill_paid') }}</dt>
                        <dd class="font-medium">{{ Money::format($payment->amount) }}</dd>
                    </div>
                @endif
                @if ($payment->booking_fee_amount > 0)
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-soft">{{ __('booking.fee.label') }}</dt>
                        <dd class="font-medium">{{ Money::format($payment->booking_fee_amount) }}</dd>
                    </div>
                @endif
                @if ($payment->tip_amount > 0)
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-soft">{{ __('payment.receipt.tip') }}</dt>
                        <dd class="font-medium">{{ Money::format($payment->tip_amount) }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-4 border-t border-line pt-2 text-base">
                    <dt class="font-semibold">{{ __('payment.receipt.total') }}</dt>
                    <dd class="font-bold">{{ Money::format($payment->total()) }}</dd>
                </div>
            </dl>

            <p class="mt-6 text-xs text-ink-soft">{{ __('payment.receipt.note') }}</p>

            <button type="button" onclick="window.print()" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-full bg-brand px-5 text-sm font-semibold text-on-brand hover:bg-brand-hover print:hidden">
                {{ __('payment.receipt.print') }}
            </button>
        </main>
    </body>
</html>
