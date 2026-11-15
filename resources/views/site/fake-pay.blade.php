@php
    use App\Support\Money;
@endphp

{{-- Підставна платіжна сторінка замість Monobank (частина 2, п. 6.5). Свідомо не схожа на банк: щоб ніхто не ввів тут картку --}}
<x-layouts::app :title="__('payment.fake.title')" noindex>
    <div class="container-site max-w-md py-12">
        <div class="rounded-card border-2 border-dashed border-warning p-6">
            <x-badge>TEST</x-badge>
            <h1 class="mt-3 text-2xl font-bold tracking-tight">{{ __('payment.fake.title') }}</h1>
            <p class="mt-2 text-sm text-ink-soft">{{ __('payment.fake.text') }}</p>

            @if ($payment->reservation)
                <p class="mt-6 text-sm text-ink-soft">{{ __('payment.invoice.reservation', ['code' => $payment->reservation->code]) }}</p>
            @else
                <p class="mt-6 text-sm text-ink-soft">{{ __('payment.invoice.destination', ['number' => $payment->order->number, 'table' => $payment->order->table->number]) }}</p>
            @endif
            <p class="mt-1 flex items-baseline justify-between gap-4">
                <span class="text-sm font-semibold">{{ __('payment.fake.amount') }}</span>
                <span class="text-3xl font-bold">{{ Money::format($payment->total()) }}</span>
            </p>

            <form method="POST" action="{{ route('fake-pay.submit', ['payment' => $payment->id]) }}" class="mt-6 flex flex-col gap-2">
                @csrf
                <x-button type="submit" name="action" value="success" size="lg">{{ __('payment.fake.success') }}</x-button>
                <x-button type="submit" name="action" value="failure" variant="secondary">{{ __('payment.fake.failure') }}</x-button>
                <x-button type="submit" name="action" value="leave" variant="text">{{ __('payment.fake.leave') }}</x-button>
            </form>
        </div>
    </div>
</x-layouts::app>
