@php
    use App\Services\Booking\AvailabilityService;
    use App\Support\LocalTime;
    use App\Support\Money;
    use Illuminate\Support\Str;

    $stepLabels = array_map(fn (string $key) => __('booking.steps.'.$key), \App\Livewire\BookingWizard::STEPS);
    $startsAt = $this->startsAt();
    $done = $this->doneReservation;
    $fee = $this->fee();
@endphp

{{-- Після переходу між кроками сторінка прокручується до початку майстра: кнопка «Далі» внизу, а новий крок починається вгорі --}}
<div
    class="container-site max-w-3xl scroll-mt-40 pb-8"
    x-data
    x-init="$watch('$wire.step', () => $el.scrollIntoView()); $watch('$wire.doneToken', () => $el.scrollIntoView())"
>
    @if ($done)
        {{-- Екран успіху --}}
        <section class="rounded-card p-6 text-center ring-1 ring-line sm:p-10" aria-labelledby="booking-done-title">
            <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-brand text-on-brand">
                <x-site.icon name="check" class="size-7" />
            </span>
            <h2 id="booking-done-title" class="mt-6 text-3xl font-bold tracking-tight">{{ __('booking.done.title') }}</h2>
            <p class="mt-3 text-ink-soft">{{ __('booking.done.text', ['email' => $done->guest_email]) }}</p>

            <dl class="mx-auto mt-8 grid max-w-md gap-x-6 gap-y-3 text-left text-sm sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <dt class="text-ink-soft">{{ __('booking.done.code') }}</dt>
                    <dd class="text-2xl font-bold tracking-widest">{{ $done->code }}</dd>
                </div>
                <div>
                    <dt class="text-ink-soft">{{ __('booking.summary.date') }}</dt>
                    <dd class="font-medium">{{ LocalTime::dateLabel($done->starts_at) }}</dd>
                </div>
                <div>
                    <dt class="text-ink-soft">{{ __('booking.summary.time') }}</dt>
                    <dd class="font-medium">{{ LocalTime::timeLabel($done->starts_at) }}–{{ LocalTime::timeLabel($done->ends_at) }}</dd>
                </div>
                <div>
                    <dt class="text-ink-soft">{{ __('booking.summary.table') }}</dt>
                    <dd class="font-medium">№{{ $done->table->number }}, {{ $done->table->hall->name }}</dd>
                </div>
                <div>
                    <dt class="text-ink-soft">{{ __('booking.summary.guests') }}</dt>
                    <dd class="font-medium">{{ $done->guests_count }}</dd>
                </div>
            </dl>

            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <x-button :href="locale_route('reservation.calendar', ['token' => $done->token])">
                    <x-site.icon name="calendar" class="size-4" />
                    {{ __('booking.done.calendar') }}
                </x-button>
                <x-button variant="secondary" :href="locale_route('reservation.show', ['token' => $done->token])">
                    {{ __('booking.done.manage') }}
                </x-button>
            </div>
            <x-button variant="text" wire:click="startOver" class="mt-4">{{ __('booking.done.again') }}</x-button>
        </section>
    @else
        <x-booking.steps :steps="$stepLabels" :current="$step" />

        {{-- Повідомлення майстра (столик зайняли, час утримання минув) оголошується програмам читання з екрана --}}
        <div role="alert" class="mt-6">
            @if ($notice !== '')
                <p class="flex items-start gap-3 rounded-card bg-muted p-4 text-sm font-medium">
                    <x-site.icon name="alert" class="size-5 shrink-0 text-danger" />
                    {{ $notice }}
                </p>
            @endif
        </div>

        {{-- Таймер утримання столика --}}
        @if ($holdExpiresAt !== null && $step >= 2)
            <p
                wire:key="hold-{{ $holdExpiresAt }}"
                x-data="holdTimer({{ $holdExpiresAt }})"
                class="mt-4 flex items-center gap-2 text-sm text-ink-soft"
            >
                <x-site.icon name="clock" class="size-4" />
                <span>{{ __('booking.table.hold') }} <span x-text="label" class="font-semibold tabular-nums text-ink" role="timer"></span></span>
            </p>
        @endif

        <div class="mt-6" wire:key="step-{{ $step }}">
            @if ($step === 1)
                {{-- Крок 1: гості, дата, час --}}
                <div class="space-y-8">
                    {{-- Про плату гість дізнається одразу, а не наприкінці (частина 1, п. 5.3) --}}
                    @if ($fee > 0)
                        <p class="flex items-start gap-3 rounded-card bg-muted p-4 text-sm">
                            <x-site.icon name="alert" class="size-5 shrink-0" />
                            <span>{{ __('booking.fee.notice', ['amount' => Money::format($fee)]) }}</span>
                        </p>
                    @endif

                    <section aria-labelledby="guests-title">
                        <h2 id="guests-title" class="text-lg font-semibold">{{ __('booking.when.guests') }}</h2>
                        <div class="mt-3 flex items-center gap-4">
                            <button type="button" wire:click="decrementGuests" @disabled($guests <= 1) class="inline-flex size-12 items-center justify-center rounded-full border border-ink transition-colors hover:bg-ink hover:text-surface disabled:pointer-events-none disabled:opacity-30">
                                <x-site.icon name="minus" />
                                <span class="sr-only">{{ __('booking.when.fewer') }}</span>
                            </button>
                            <output class="w-10 text-center text-3xl font-bold tabular-nums" aria-live="polite" aria-labelledby="guests-title">{{ $guests }}</output>
                            <button type="button" wire:click="incrementGuests" @disabled($guests >= $this->maxGuests()) class="inline-flex size-12 items-center justify-center rounded-full border border-ink transition-colors hover:bg-ink hover:text-surface disabled:pointer-events-none disabled:opacity-30">
                                <x-site.icon name="plus" />
                                <span class="sr-only">{{ __('booking.when.more') }}</span>
                            </button>
                        </div>
                        @if ($guests >= $this->maxGuests())
                            <p class="mt-3 text-sm text-ink-soft">{{ __('booking.when.big_party', ['count' => $this->maxGuests()]) }}</p>
                        @endif
                    </section>

                    <section aria-labelledby="date-title">
                        <h2 id="date-title" class="text-lg font-semibold">{{ __('booking.when.date') }}</h2>

                        {{-- Блочна форма директиви: однорядкову Blade склеює з наступним закриттям блоку php нижче у файлі --}}
                        @php
                            $monthStart = LocalTime::parse($month.'-01');
                        @endphp
                        <div class="mt-3 max-w-md rounded-card p-4 ring-1 ring-line">
                            <div class="flex items-center justify-between">
                                <button type="button" wire:click="previousMonth" @disabled(! $this->canGoToPreviousMonth()) class="inline-flex size-11 items-center justify-center rounded-full hover:bg-muted disabled:pointer-events-none disabled:opacity-30">
                                    <x-site.icon name="chevron-left" />
                                    <span class="sr-only">{{ __('booking.when.prev_month') }}</span>
                                </button>
                                <p class="font-semibold" aria-live="polite">{{ Str::ucfirst($monthStart->locale(app()->getLocale())->isoFormat('MMMM YYYY')) }}</p>
                                <button type="button" wire:click="nextMonth" @disabled(! $this->canGoToNextMonth()) class="inline-flex size-11 items-center justify-center rounded-full hover:bg-muted disabled:pointer-events-none disabled:opacity-30">
                                    <x-site.icon name="chevron-right" />
                                    <span class="sr-only">{{ __('booking.when.next_month') }}</span>
                                </button>
                            </div>

                            <div class="mt-2 grid grid-cols-7 text-center text-xs text-ink-soft" aria-hidden="true">
                                @foreach ($this->calendar[0] as $weekday)
                                    <span class="py-2">{{ LocalTime::parse($weekday['date'])->locale(app()->getLocale())->minDayName }}</span>
                                @endforeach
                            </div>

                            <div class="grid grid-cols-7 gap-1" data-choice-group>
                                @foreach ($this->calendar as $week)
                                    @foreach ($week as $day)
                                        @if ($day['inMonth'])
                                            <button
                                                type="button"
                                                wire:key="day-{{ $day['date'] }}"
                                                wire:click="selectDate('{{ $day['date'] }}')"
                                                data-choice
                                                @disabled(! $day['bookable'])
                                                aria-label="{{ LocalTime::dateLabel(LocalTime::parse($day['date'])) }}"
                                                aria-pressed="{{ json_encode($date === $day['date']) }}"
                                                {{-- data-loading Livewire ставить на натиснутий елемент до відповіді сервера:
                                                     вибір підсвічується одразу, а не після запиту (так само для часу, залу й столика) --}}
                                                @class([
                                                    'flex aspect-square min-h-11 items-center justify-center rounded-full text-sm font-medium transition-colors duration-150 data-loading:bg-brand data-loading:text-on-brand',
                                                    'bg-brand text-on-brand switching:bg-transparent switching:text-ink' => $date === $day['date'],
                                                    'hover:bg-muted' => $date !== $day['date'] && $day['bookable'],
                                                    'text-ink-soft/40 line-through' => ! $day['bookable'],
                                                    'ring-1 ring-ink' => $day['today'] && $date !== $day['date'],
                                                ])
                                            >{{ $day['day'] }}</button>
                                        @else
                                            <span></span>
                                        @endif
                                    @endforeach
                                @endforeach
                            </div>
                        </div>
                    </section>

                    <section aria-labelledby="time-title">
                        <h2 id="time-title" class="text-lg font-semibold">{{ __('booking.when.time') }}</h2>

                        @if ($date === null)
                            <p class="mt-3 text-ink-soft">{{ __('booking.when.pick_date') }}</p>
                        @elseif ($this->timeSlots === [])
                            <p class="mt-3 text-ink-soft">{{ __('booking.when.no_slots') }}</p>
                        @else
                            <div class="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-6" data-choice-group>
                                @foreach ($this->timeSlots as $slot)
                                    <button
                                        type="button"
                                        wire:key="slot-{{ $slot['time'] }}"
                                        wire:click="selectTime('{{ $slot['time'] }}')"
                                        data-choice
                                        @disabled(! $slot['available'])
                                        aria-pressed="{{ json_encode($time === $slot['time']) }}"
                                        @if (! $slot['available']) aria-label="{{ $slot['time'] }}, {{ __('booking.when.slot_taken') }}" @endif
                                        @class([
                                            'min-h-11 rounded-full border text-sm font-medium tabular-nums transition-colors duration-150 data-loading:border-brand data-loading:bg-brand data-loading:text-on-brand',
                                            'border-brand bg-brand text-on-brand switching:border-line switching:bg-transparent switching:text-ink' => $time === $slot['time'],
                                            'border-line hover:border-ink' => $time !== $slot['time'] && $slot['available'],
                                            'border-line text-ink-soft/40 line-through' => ! $slot['available'],
                                        ])
                                    >{{ $slot['time'] }}</button>
                                @endforeach
                            </div>
                            <p class="mt-3 text-sm text-ink-soft">{{ __('booking.when.duration', ['hours' => $durationLabel]) }}</p>
                        @endif
                    </section>

                    <div class="flex justify-end border-t border-line pt-6">
                        <x-button size="lg" wire:click="chooseTable" :disabled="$time === null" wire:loading.attr="disabled">
                            {{ __('booking.actions.next') }}
                            <x-site.icon name="arrow-right" class="size-5" />
                        </x-button>
                    </div>
                </div>
            @elseif ($step === 2)
                {{-- Крок 2: столик --}}
                @php
                    $hall = $this->halls->firstWhere('id', $hallId) ?? $this->halls->first();
                    $freeTables = collect();

                    if ($hall) {
                        $freeTables = $hall->tables->filter(fn ($table) => ($this->tableStates[$table->id] ?? null) === AvailabilityService::STATE_FREE);
                    }
                @endphp

                <div class="space-y-6">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">{{ __('booking.table.heading') }}</h2>
                            <p class="text-sm text-ink-soft">
                                {{ LocalTime::dateLabel($startsAt) }}, {{ $time }} · {{ __('booking.summary.guests') }}: {{ $guests }}
                            </p>
                        </div>
                        <x-button variant="secondary" wire:click="autoSelect">{{ __('booking.table.auto') }}</x-button>
                    </div>

                    @if ($this->halls->count() > 1)
                        <div role="group" aria-label="{{ __('booking.table.halls') }}" class="flex flex-wrap gap-2" data-choice-group>
                            @foreach ($this->halls as $option)
                                <button
                                    type="button"
                                    wire:click="selectHall({{ $option->id }})"
                                    data-choice
                                    aria-pressed="{{ json_encode($hall && $option->id === $hall->id) }}"
                                    class="inline-flex min-h-11 items-center rounded-full border border-line px-4 text-sm font-medium transition-colors hover:border-ink aria-pressed:border-ink aria-pressed:bg-ink aria-pressed:text-surface data-loading:border-ink data-loading:bg-ink data-loading:text-surface switching:border-line switching:bg-transparent switching:text-ink"
                                >{{ $option->name }}</button>
                            @endforeach
                        </div>
                    @endif

                    @if ($hall)
                        {{-- Схема й запасний список — одна група вибору: натискання в списку одразу знімає підсвітку й на схемі --}}
                        <div class="space-y-6" data-choice-group>
                        <x-booking.floor-plan :hall="$hall" :states="$this->tableStates" :selected="$tableId" wire:key="plan-{{ $hall->id }}" />

                        <ul class="flex flex-wrap gap-x-5 gap-y-2 text-xs text-ink-soft" aria-label="{{ __('booking.table.legend') }}">
                            <li class="flex items-center gap-2"><span class="size-4 rounded border-2 border-ink bg-surface"></span>{{ __('booking.table.state.free') }}</li>
                            <li class="flex items-center gap-2"><span class="size-4 rounded bg-brand"></span>{{ __('booking.table.state.selected') }}</li>
                            <li class="flex items-center gap-2"><span class="size-4 rounded border-2 border-dashed border-ink-soft/50 bg-muted"></span>{{ __('booking.table.state.busy') }} / {{ __('booking.table.state.unfit') }}</li>
                        </ul>

                        {{-- Запасний список: на малому екрані зручніший за схему --}}
                        <section aria-labelledby="table-list-title">
                            <h3 id="table-list-title" class="text-sm font-semibold">{{ __('booking.table.list') }}</h3>
                            @if ($freeTables->isEmpty())
                                <p class="mt-2 text-sm text-ink-soft">{{ __('booking.table.none') }}</p>
                            @else
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($freeTables as $table)
                                        <button
                                            type="button"
                                            wire:key="list-table-{{ $table->id }}"
                                            wire:click="selectTable({{ $table->id }})"
                                            data-choice
                                            aria-pressed="{{ json_encode($tableId === $table->id) }}"
                                            class="inline-flex min-h-11 items-center gap-2 rounded-full border border-line px-4 text-sm font-medium transition-colors hover:border-ink aria-pressed:border-brand aria-pressed:bg-brand aria-pressed:text-on-brand data-loading:border-brand data-loading:bg-brand data-loading:text-on-brand switching:border-line switching:bg-transparent switching:text-ink"
                                        >
                                            {{ __('booking.table.name', ['number' => $table->number]) }}
                                            <span class="font-normal opacity-80">· {{ trans_choice('booking.table.seats', $table->seats) }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                        </div>
                    @endif

                    <div class="flex justify-between border-t border-line pt-6">
                        <x-button variant="secondary" size="lg" wire:click="back">{{ __('booking.actions.back') }}</x-button>
                        <x-button size="lg" wire:click="enterContacts" :disabled="$tableId === null" wire:loading.attr="disabled">
                            {{ __('booking.actions.next') }}
                            <x-site.icon name="arrow-right" class="size-5" />
                        </x-button>
                    </div>
                </div>
            @elseif ($step === 3)
                {{-- Крок 3: контакти --}}
                <form wire:submit="review" class="space-y-5" novalidate>
                    <x-form.field :label="__('booking.contacts.name')" for="booking-name" error="name">
                        <x-form.input id="booking-name" wire:model="name" error="name" autocomplete="name" maxlength="80" required />
                    </x-form.field>

                    <x-form.field :label="__('booking.contacts.phone')" for="booking-phone" error="phone" :hint="__('booking.contacts.phone_hint')">
                        <div x-data="phoneMask" wire:ignore>
                            <x-form.input id="booking-phone" type="tel" x-model="display" x-on:input="onInput()" error="phone" autocomplete="tel" inputmode="tel" placeholder="+380" required />
                        </div>
                    </x-form.field>

                    <x-form.field :label="__('booking.contacts.email')" for="booking-email" error="email" :hint="__('booking.contacts.email_hint')">
                        <x-form.input id="booking-email" type="email" wire:model="email" error="email" autocomplete="email" maxlength="255" required />
                    </x-form.field>

                    <x-form.field :label="__('booking.contacts.comment')" for="booking-comment" error="comment" :hint="__('booking.contacts.comment_hint')" optional>
                        <x-form.input id="booking-comment" multiline wire:model="comment" error="comment" maxlength="500" />
                    </x-form.field>

                    <div class="space-y-1.5">
                        <label class="flex cursor-pointer items-start gap-3 text-sm">
                            <input type="checkbox" wire:model="consent" aria-describedby="booking-consent-error" class="mt-0.5 size-5 shrink-0 accent-brand">
                            <span class="[&_a]:text-brand [&_a]:underline [&_a]:underline-offset-2">{!! __('booking.contacts.consent', ['offer' => locale_route('offer'), 'privacy' => locale_route('privacy')]) !!}</span>
                        </label>
                        <p id="booking-consent-error" role="alert" class="text-sm font-medium text-danger">{{ $errors->first('consent') }}</p>
                    </div>

                    <div class="flex justify-between border-t border-line pt-6">
                        <x-button variant="secondary" size="lg" wire:click="back">{{ __('booking.actions.back') }}</x-button>
                        <x-button size="lg" type="submit" wire:loading.attr="disabled">
                            {{ __('booking.actions.next') }}
                            <x-site.icon name="arrow-right" class="size-5" />
                        </x-button>
                    </div>
                </form>
            @elseif ($step === 4)
                {{-- Крок 4: попереднє замовлення (необов'язкове) --}}
                @php
                    $preorderAvailable = $this->preorderAvailable();
                @endphp

                <div class="space-y-6">
                    <div>
                        <h2 class="text-lg font-semibold">{{ __('booking.preorder.heading') }}</h2>
                        <p class="mt-1 text-sm text-ink-soft">{{ __('booking.preorder.intro') }}</p>
                    </div>

                    @if ($preorderAvailable)
                        <div role="group" aria-label="{{ __('booking.preorder.heading') }}" class="grid gap-3 sm:grid-cols-2" data-choice-group>
                            <button
                                type="button"
                                wire:click="choosePreorder(false)"
                                data-choice
                                aria-pressed="{{ json_encode(! $wantsPreorder) }}"
                                class="rounded-card border border-line p-4 text-left transition-colors hover:border-ink aria-pressed:border-brand aria-pressed:ring-1 aria-pressed:ring-brand"
                            >
                                <span class="block font-semibold">{{ __('booking.preorder.table_only') }}</span>
                                <span class="mt-1 block text-sm text-ink-soft">{{ __('booking.preorder.table_only_hint') }}</span>
                            </button>
                            <button
                                type="button"
                                wire:click="choosePreorder(true)"
                                data-choice
                                aria-pressed="{{ json_encode($wantsPreorder) }}"
                                class="rounded-card border border-line p-4 text-left transition-colors hover:border-ink aria-pressed:border-brand aria-pressed:ring-1 aria-pressed:ring-brand"
                            >
                                <span class="block font-semibold">{{ __('booking.preorder.with_dishes') }}</span>
                                <span class="mt-1 block text-sm text-ink-soft">{{ __('booking.preorder.with_dishes_hint', ['time' => $time]) }}</span>
                            </button>
                        </div>

                        @if ($wantsPreorder)
                            <x-booking.preorder-menu :categories="$this->preorderMenu" :cart="$cart" />

                            {{-- Підсумок кошика тримається внизу екрана: меню довге, а сума має бути перед очима --}}
                            <div class="sticky bottom-3 flex items-center justify-between gap-4 rounded-card bg-surface p-4 shadow-lg ring-1 ring-line" aria-live="polite">
                                <p class="text-sm text-ink-soft">{{ trans_choice('booking.preorder.count', $this->cartCount()) }}</p>
                                <p class="text-lg font-bold">{{ Money::format($this->cartTotal()) }}</p>
                            </div>

                            <p class="text-sm text-ink-soft">{{ __('booking.preorder.terms', ['hours' => $preorderCancelLabel]) }}</p>
                        @endif
                    @else
                        <p class="rounded-card bg-muted p-4 text-sm text-ink-soft">{{ __('booking.preorder.unavailable', ['hours' => $preorderLeadLabel]) }}</p>
                    @endif

                    <div class="flex justify-between border-t border-line pt-6">
                        <x-button variant="secondary" size="lg" wire:click="back">{{ __('booking.actions.back') }}</x-button>
                        <x-button size="lg" wire:click="toSummary" wire:loading.attr="disabled">
                            {{ __('booking.actions.next') }}
                            <x-site.icon name="arrow-right" class="size-5" />
                        </x-button>
                    </div>
                </div>
            @else
                {{-- Крок 5: підтвердження --}}
                @php
                    $lines = [];

                    if ($wantsPreorder) {
                        $lines = $this->cartLines;
                    }

                    $total = $this->totalToPay();
                @endphp

                <div class="space-y-6">
                    <h2 class="text-lg font-semibold">{{ __('booking.summary.heading') }}</h2>

                    <dl class="divide-y divide-line rounded-card ring-1 ring-line">
                        <div class="flex items-center justify-between gap-4 p-4">
                            <div>
                                <dt class="text-sm text-ink-soft">{{ __('booking.summary.date') }}, {{ mb_strtolower(__('booking.summary.time')) }}</dt>
                                <dd class="font-medium">{{ LocalTime::dateLabel($startsAt) }}, {{ $time }} · {{ __('booking.summary.guests') }}: {{ $guests }}</dd>
                            </div>
                            <x-button variant="text" wire:click="goTo(1)">{{ __('booking.actions.change') }}</x-button>
                        </div>
                        <div class="flex items-center justify-between gap-4 p-4">
                            <div>
                                <dt class="text-sm text-ink-soft">{{ __('booking.summary.table') }}</dt>
                                <dd class="font-medium">№{{ $this->table?->number }}, {{ $this->table?->hall->name }}</dd>
                            </div>
                            <x-button variant="text" wire:click="goTo(2)">{{ __('booking.actions.change') }}</x-button>
                        </div>
                        <div class="flex items-center justify-between gap-4 p-4">
                            <div class="min-w-0">
                                <dt class="text-sm text-ink-soft">{{ __('booking.steps.contacts') }}</dt>
                                <dd class="font-medium break-words">{{ $name }}, {{ $phone }}, {{ $email }}</dd>
                                @if (trim($comment) !== '')
                                    <dd class="mt-1 text-sm text-ink-soft break-words">{{ $comment }}</dd>
                                @endif
                            </div>
                            <x-button variant="text" wire:click="goTo(3)">{{ __('booking.actions.change') }}</x-button>
                        </div>
                        <div class="flex items-start justify-between gap-4 p-4">
                            <div class="min-w-0 flex-1">
                                <dt class="text-sm text-ink-soft">{{ __('booking.preorder.title') }}</dt>
                                @if ($lines === [])
                                    <dd class="font-medium">{{ __('booking.preorder.none') }}</dd>
                                @else
                                    <dd>
                                        <ul class="mt-1 space-y-1">
                                            @foreach ($lines as $line)
                                                <li class="flex justify-between gap-4 text-sm">
                                                    <span class="min-w-0 break-words font-medium">{{ $line['dish']->name }} × {{ $line['quantity'] }}</span>
                                                    <span class="shrink-0 tabular-nums">{{ Money::format($line['total']) }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </dd>
                                @endif
                            </div>
                            <x-button variant="text" wire:click="goTo(4)">{{ __('booking.actions.change') }}</x-button>
                        </div>
                    </dl>

                    @if ($total > 0)
                        {{-- «Бронювання + Страви = Разом» (частина 1, п. 5.3) --}}
                        <dl class="space-y-2 rounded-card bg-muted p-4 text-sm">
                            @if ($fee > 0)
                                <div class="flex justify-between gap-4">
                                    <dt>{{ __('booking.fee.label') }}</dt>
                                    <dd class="font-medium tabular-nums">{{ Money::format($fee) }}</dd>
                                </div>
                            @endif
                            @if ($lines !== [])
                                <div class="flex justify-between gap-4">
                                    <dt>{{ __('booking.preorder.dishes') }}</dt>
                                    <dd class="font-medium tabular-nums">{{ Money::format($this->cartTotal()) }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between gap-4 border-t border-line pt-2 text-base">
                                <dt class="font-semibold">{{ __('booking.summary.total') }}</dt>
                                <dd class="font-bold tabular-nums">{{ Money::format($total) }}</dd>
                            </div>
                        </dl>

                        <div class="space-y-1 text-sm text-ink-soft">
                            <p>{{ __('booking.summary.pay_online', ['minutes' => \App\Services\Booking\BookingSettings::paymentHold()]) }}</p>
                            @if ($fee > 0)
                                <p>{{ __('booking.fee.terms', ['hours' => $feeRefundLabel]) }}</p>
                            @endif
                            @if ($lines !== [])
                                <p>{{ __('booking.preorder.terms', ['hours' => $preorderCancelLabel]) }}</p>
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-ink-soft">{{ __('booking.summary.free') }}</p>
                    @endif

                    <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-between">
                        <x-button variant="secondary" size="lg" wire:click="back">{{ __('booking.actions.back') }}</x-button>
                        <x-button size="lg" wire:click="submit" wire:loading.attr="disabled" wire:target="submit">
                            @if ($total > 0)
                                <span wire:loading.remove wire:target="submit">{{ __('booking.actions.book_and_pay') }}</span>
                            @else
                                <span wire:loading.remove wire:target="submit">{{ __('booking.actions.book') }}</span>
                            @endif
                            <span wire:loading wire:target="submit">{{ __('booking.actions.booking') }}</span>
                        </x-button>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
