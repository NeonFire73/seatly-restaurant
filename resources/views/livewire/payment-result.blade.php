@php
    use App\Enums\PaymentStatus;
    use App\Support\Money;

    $payment = $this->payment;
    $status = $payment->status;
    $tableUrl = locale_route('table.show', ['token' => $payment->order->table->qr_token]);

    // «Створено» після повернення гостя означає, що він пішов із платіжної сторінки, не заплативши
    $state = match ($status) {
        PaymentStatus::Created => 'unfinished',
        PaymentStatus::Processing => 'pending',
        PaymentStatus::Success => 'success',
        PaymentStatus::Failure => 'failure',
        PaymentStatus::Expired => 'expired',
        PaymentStatus::Refunded => 'refunded',
    };

    $icon = match ($state) {
        'success' => 'check',
        'failure', 'expired' => 'alert',
        default => 'clock',
    };

    $iconTone = match ($state) {
        'success' => 'bg-success text-on-brand',
        'failure', 'expired' => 'bg-danger text-on-brand',
        default => 'bg-muted text-ink',
    };
@endphp

<div class="container-site max-w-md py-12">
    <div
        class="rounded-card border border-line p-6 text-center"
        role="status"
        aria-live="polite"
        @if ($status->isPending()) wire:poll.3s="refreshStatus" @endif
    >
        <span class="mx-auto flex size-14 items-center justify-center rounded-full {{ $iconTone }}">
            <x-site.icon :name="$icon" class="size-7" />
        </span>

        <h1 class="mt-4 text-2xl font-bold tracking-tight">{{ __('payment.result.'.$state.'_title') }}</h1>
        <p class="mt-2 text-sm text-ink-soft">{{ __('payment.result.'.$state.'_text') }}</p>

        <dl class="mt-6 space-y-2 border-t border-line pt-4 text-left text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-ink-soft">{{ __('payment.result.bill') }}</dt>
                <dd class="font-medium">{{ Money::format($payment->amount) }}</dd>
            </div>
            @if ($payment->tip_amount > 0)
                <div class="flex justify-between gap-4">
                    <dt class="text-ink-soft">{{ __('payment.result.tip') }}</dt>
                    <dd class="font-medium">{{ Money::format($payment->tip_amount) }}</dd>
                </div>
            @endif
            <div class="flex justify-between gap-4 border-t border-line pt-2 text-base">
                @if ($state === 'success')
                    <dt class="font-semibold">{{ __('payment.result.total') }}</dt>
                @else
                    <dt class="font-semibold">{{ __('payment.result.to_pay') }}</dt>
                @endif
                <dd class="font-bold">{{ Money::format($payment->total()) }}</dd>
            </div>
        </dl>

        <div class="mt-6 flex flex-col gap-2">
            @if ($state === 'success')
                <x-button :href="locale_route('payment.receipt', ['payment' => $payment->id])" target="_blank" rel="noopener">{{ __('payment.result.receipt') }}</x-button>
                <x-button variant="secondary" :href="$tableUrl">{{ __('payment.result.back') }}</x-button>
            @elseif ($state === 'unfinished')
                @if ($payment->page_url !== null)
                    <x-button :href="$payment->page_url">{{ __('payment.result.continue') }}</x-button>
                @endif
                <x-button variant="secondary" :href="$tableUrl">{{ __('payment.result.back') }}</x-button>
            @elseif ($state === 'failure' || $state === 'expired')
                <x-button :href="$tableUrl">{{ __('payment.result.retry') }}</x-button>
            @elseif ($state === 'refunded')
                <x-button variant="secondary" :href="$tableUrl">{{ __('payment.result.back') }}</x-button>
            @endif
        </div>
    </div>
</div>
