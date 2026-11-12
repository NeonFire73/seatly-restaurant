@php
    use App\Enums\ReservationStatus;
    use App\Services\SiteContent;
    use App\Support\LocalTime;

    $reservation = $this->reservation;
    $phone = SiteContent::contacts()['phone'];
    $isLive = $reservation->status->occupiesTable();
@endphp

<div class="container-site max-w-2xl pb-8">
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
            @if ($reservation->comment)
                <div class="sm:col-span-2">
                    <dt class="text-ink-soft">{{ __('booking.summary.comment') }}</dt>
                    <dd class="break-words">{{ $reservation->comment }}</dd>
                </div>
            @endif
        </dl>

        <div x-data class="flex flex-col gap-3 border-t border-line p-5 sm:flex-row">
            @if ($isLive)
                <x-button :href="locale_route('reservation.calendar', ['token' => $reservation->token])">
                    <x-site.icon name="calendar" class="size-4" />
                    {{ __('booking.page.calendar') }}
                </x-button>
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
                    <p class="mt-2 text-ink-soft">{{ __('booking.page.cancel_text') }}</p>
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
