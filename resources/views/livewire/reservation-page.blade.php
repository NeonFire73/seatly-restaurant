@php
    use App\Enums\OrderItemStatus;
    use App\Enums\OrderStatus;
    use App\Enums\ReservationStatus;
    use App\Services\SiteContent;
    use App\Support\LocalTime;
    use App\Support\Money;

    $reservation = $this->reservation;
    $preorder = $this->preorder;
    $phone = SiteContent::contacts()['phone'];
    $isLive = $reservation->status->occupiesTable();
    $isPending = $reservation->status === ReservationStatus::PendingPayment;
    $feeState = $reservation->feeState();
    $due = $this->amountDue();
    $preorderUnpaid = $preorder !== null && $preorder->status === OrderStatus::AwaitingPayment;
@endphp

{{-- Поки бронювання чекає оплати, статус перечитується: його змінює вебхук або фонова задача, а не ця сторінка --}}
<div class="container-site max-w-2xl pb-8" @if ($isPending) wire:poll.10s @endif>
    <div role="alert">
        @if ($notice !== '')
            <p class="mb-6 flex items-start gap-3 rounded-card bg-muted p-4 text-sm font-medium">
                <x-site.icon name="alert" class="size-5 shrink-0" />
                {{ $notice }}
            </p>
        @endif
    </div>

    <section class="rounded-card ring-1 ring-line">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line p-5">
            <div>
                <p class="text-sm text-ink-soft">{{ __('booking.summary.code') }}</p>
                <p class="text-2xl font-bold tracking-widest">{{ $reservation->code }}</p>
            </div>
            {{-- Фірмовий колір — лише для чинного бронювання; решта статусів нейтральні й відрізняються текстом --}}
            @if ($reservation->status === ReservationStatus::Confirmed || $reservation->status === ReservationStatus::Seated)
                <x-badge tone="brand" class="px-3 py-1 text-sm">{{ __('booking.status.'.$reservation->status->value) }}</x-badge>
            @else
                <x-badge class="px-3 py-1 text-sm">{{ __('booking.status.'.$reservation->status->value) }}</x-badge>
            @endif
        </div>

        {{-- Неоплачене бронювання: скільки лишилося часу й кнопка оплати --}}
        @if ($isPending && $due > 0)
            <div class="space-y-3 border-b border-line bg-muted p-5">
                <p class="text-sm">{{ __('booking.page.pending_text') }}</p>
                @if ($reservation->expires_at !== null && $reservation->expires_at->isFuture())
                    <p
                        wire:key="pay-hold-{{ $reservation->expires_at->getTimestamp() }}"
                        x-data="holdTimer({{ $reservation->expires_at->getTimestamp() }})"
                        class="flex items-center gap-2 text-sm text-ink-soft"
                    >
                        <x-site.icon name="clock" class="size-4" />
                        <span>{{ __('booking.page.pending_hold') }} <span x-text="label" class="font-semibold tabular-nums text-ink" role="timer"></span></span>
                    </p>
                @endif
                <x-button wire:click="pay" wire:loading.attr="disabled" wire:target="pay">
                    {{ __('booking.page.pay', ['amount' => Money::format($due)]) }}
                </x-button>
            </div>
        @endif

        <dl class="grid gap-x-6 gap-y-4 p-5 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-ink-soft">{{ __('booking.summary.date') }}</dt>
                <dd class="text-base font-medium">{{ LocalTime::dateLabel($reservation->starts_at) }}</dd>
            </div>
            <div>
                <dt class="text-ink-soft">{{ __('booking.summary.time') }}</dt>
                <dd class="text-base font-medium">{{ LocalTime::timeLabel($reservation->starts_at) }}–{{ LocalTime::timeLabel($reservation->ends_at) }}</dd>
            </div>
            <div>
                <dt class="text-ink-soft">{{ __('booking.summary.table') }}</dt>
                <dd class="text-base font-medium">№{{ $reservation->table->number }}, {{ $reservation->table->hall->name }}</dd>
            </div>
            <div>
                <dt class="text-ink-soft">{{ __('booking.summary.guests') }}</dt>
                <dd class="text-base font-medium">{{ $reservation->guests_count }}</dd>
            </div>
            <div>
                <dt class="text-ink-soft">{{ __('booking.summary.name') }}</dt>
                <dd class="text-base font-medium break-words">{{ $reservation->guest_name }}</dd>
            </div>
            @if ($feeState !== null)
                <div>
                    <dt class="text-ink-soft">{{ __('booking.fee.label') }}</dt>
                    <dd class="text-base font-medium">{{ Money::format($reservation->fee_amount) }} · {{ __('booking.fee.state.'.$feeState) }}</dd>
                </div>
            @endif
            @if ($reservation->comment)
                <div class="sm:col-span-2">
                    <dt class="text-ink-soft">{{ __('booking.summary.comment') }}</dt>
                    <dd class="break-words">{{ $reservation->comment }}</dd>
                </div>
            @endif
        </dl>

        {{-- Попереднє замовлення --}}
        @if ($preorder !== null && ! $addingPreorder)
            <div class="border-t border-line p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-semibold">{{ __('booking.preorder.title') }}</h2>
                    @if ($preorderUnpaid)
                        <x-badge>{{ __('booking.preorder.state.unpaid') }}</x-badge>
                    @else
                        <x-badge tone="brand">{{ __('booking.preorder.state.paid') }}</x-badge>
                    @endif
                </div>
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach ($preorder->items as $item)
                        @if ($item->status !== OrderItemStatus::Cancelled)
                            <li class="flex justify-between gap-4">
                                <span class="min-w-0 break-words">{{ $item->name }} × {{ $item->quantity }}</span>
                                <span class="shrink-0 tabular-nums">{{ Money::format($item->total()) }}</span>
                            </li>
                        @endif
                    @endforeach
                    <li class="flex justify-between gap-4 border-t border-line pt-2 font-semibold">
                        <span>{{ __('booking.summary.total') }}</span>
                        <span class="tabular-nums">{{ Money::format($preorder->subtotal) }}</span>
                    </li>
                </ul>
                @if (! $preorderUnpaid)
                    <p class="mt-3 text-sm text-ink-soft">{{ __('booking.preorder.serve_at', ['time' => LocalTime::timeLabel($reservation->starts_at)]) }}</p>
                @elseif (! $isPending && $due > 0)
                    {{-- Замовлення додано до вже підтвердженого бронювання й ще не оплачене --}}
                    <p class="mt-3 text-sm text-ink-soft">{{ __('booking.preorder.unpaid_hint') }}</p>
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                        <x-button wire:click="pay" wire:loading.attr="disabled" wire:target="pay">{{ __('booking.page.pay', ['amount' => Money::format($due)]) }}</x-button>
                        @if ($this->canAddPreorder())
                            <x-button variant="secondary" wire:click="startPreorder">{{ __('booking.preorder.edit') }}</x-button>
                        @endif
                    </div>
                @endif
            </div>
        @elseif ($addingPreorder)
            <div class="space-y-5 border-t border-line p-5">
                <div>
                    <h2 class="font-semibold">{{ __('booking.preorder.heading') }}</h2>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('booking.preorder.terms', ['hours' => $preorderCancelLabel]) }}</p>
                </div>

                <x-booking.preorder-menu :categories="$this->preorderMenu" :cart="$cart" />

                <div class="sticky bottom-3 flex flex-wrap items-center justify-between gap-3 rounded-card bg-surface p-4 shadow-lg ring-1 ring-line">
                    <p aria-live="polite">
                        <span class="block text-sm text-ink-soft">{{ trans_choice('booking.preorder.count', $this->cartCount()) }}</span>
                        <span class="block text-lg font-bold">{{ Money::format($this->cartTotal()) }}</span>
                    </p>
                    <div class="flex gap-2">
                        <x-button variant="secondary" wire:click="cancelPreorderEdit">{{ __('booking.actions.back') }}</x-button>
                        <x-button wire:click="submitPreorder" wire:loading.attr="disabled" wire:target="submitPreorder" :disabled="$this->cartCount() === 0">
                            {{ __('booking.preorder.pay') }}
                        </x-button>
                    </div>
                </div>
            </div>
        @elseif ($this->canAddPreorder())
            <div class="border-t border-line p-5">
                <h2 class="font-semibold">{{ __('booking.preorder.title') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ __('booking.preorder.offer', ['hours' => $preorderLeadLabel]) }}</p>
                <x-button variant="secondary" wire:click="startPreorder" class="mt-3">{{ __('booking.preorder.start') }}</x-button>
            </div>
        @endif

        <div x-data class="flex flex-col gap-3 border-t border-line p-5 sm:flex-row">
            @if ($isLive)
                @if (! $isPending)
                    <x-button :href="locale_route('reservation.calendar', ['token' => $reservation->token])">
                        <x-site.icon name="calendar" class="size-4" />
                        {{ __('booking.page.calendar') }}
                    </x-button>
                @endif
            @else
                <x-button :href="locale_route('booking')">{{ __('booking.page.new') }}</x-button>
            @endif

            @if ($reservation->isCancellableByGuest())
                <x-button variant="secondary" x-on:click="$refs.cancelDialog.showModal()">{{ __('booking.page.cancel') }}</x-button>

                {{-- Нативний dialog сам утримує фокус і закривається клавішею Esc --}}
                <dialog
                    x-ref="cancelDialog"
                    x-on:click.self="$el.close()"
                    aria-labelledby="cancel-dialog-title"
                    class="m-auto w-[calc(100%-2rem)] max-w-md rounded-card bg-surface p-6 text-ink backdrop:bg-ink/50"
                >
                    <h2 id="cancel-dialog-title" class="text-xl font-bold tracking-tight">{{ __('booking.page.cancel_title') }}</h2>
                    {{-- Що буде з грошима, гість бачить до скасування; рахує це те саме правило, що й саме повернення --}}
                    @if ($refund['paid'] === 0)
                        <p class="mt-2 text-ink-soft">{{ __('booking.page.cancel_text') }}</p>
                    @elseif ($refund['refund'] === $refund['paid'])
                        <p class="mt-2 text-ink-soft">{{ __('booking.page.cancel_refund_full', ['amount' => Money::format($refund['refund'])]) }}</p>
                    @elseif ($refund['refund'] > 0)
                        <p class="mt-2 text-ink-soft">{{ __('booking.page.cancel_refund_part', ['amount' => Money::format($refund['refund']), 'paid' => Money::format($refund['paid'])]) }}</p>
                    @else
                        <p class="mt-2 text-ink-soft">{{ __('booking.page.cancel_refund_none', ['paid' => Money::format($refund['paid'])]) }}</p>
                    @endif
                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <x-button variant="secondary" x-on:click="$refs.cancelDialog.close()">{{ __('booking.page.cancel_keep') }}</x-button>
                        <x-button wire:click="cancel" x-on:click="$refs.cancelDialog.close()">{{ __('booking.page.cancel_confirm') }}</x-button>
                    </div>
                </dialog>
            @endif
        </div>
    </section>

    @if ($phone)
        <p class="mt-6 text-sm text-ink-soft">
            {{ __('booking.page.questions') }}
            <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" class="font-medium text-ink underline underline-offset-2">{{ $phone }}</a>
        </p>
    @endif
</div>
