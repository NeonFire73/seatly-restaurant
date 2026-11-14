@props([
    'hall',
    // ідентифікатор столика → free | busy | unfit
    'states',
    'selected' => null,
])

@php
    use App\Enums\TableShape;
    use App\Services\Booking\AvailabilityService;
@endphp

{{-- Схема залу (частина 1, п. 5.3): столики за координатами з адмінки. Стан передається формою лінії
     й текстом для програм читання з екрана, а не лише кольором. На телефоні схема прокручується вбік. --}}
<div {{ $attributes->class('overflow-x-auto rounded-card ring-1 ring-line') }}>
    <svg
        viewBox="0 0 {{ $hall->plan_width }} {{ $hall->plan_height }}"
        role="group"
        aria-label="{{ __('booking.table.plan', ['hall' => $hall->name]) }}"
        class="block w-full min-w-[640px] bg-muted"
    >
        @foreach ($hall->tables as $table)
            @php
                $state = $states[$table->id] ?? AvailabilityService::STATE_BUSY;
                $isSelected = $selected === $table->id;
                $isFree = $state === AvailabilityService::STATE_FREE;
                $stateLabel = __('booking.table.state.'.$state);

                if ($isSelected) {
                    $stateLabel = __('booking.table.state.selected');
                }

                $cx = $table->x + $table->width / 2;
                $cy = $table->y + $table->height / 2;

                $shapeClass = 'fill-muted stroke-ink-soft/50 [stroke-dasharray:10_8]';
                $textClass = 'fill-ink-soft';

                if ($isSelected) {
                    // group-switching: натиснуто інший столик — цей одразу виглядає вільним
                    $shapeClass = 'fill-brand stroke-brand group-switching:fill-surface group-switching:stroke-ink group-focus-visible:stroke-brand-hover';
                    $textClass = 'fill-on-brand group-switching:fill-ink';
                } elseif ($isFree) {
                    // group-data-loading: столик зафарбовується одразу після натискання, до відповіді сервера
                    $shapeClass = 'fill-surface stroke-ink group-hover:fill-ink/5 group-data-loading:fill-brand group-data-loading:stroke-brand';
                    $textClass = 'fill-ink group-data-loading:fill-on-brand';
                }
            @endphp

            <g
                wire:key="plan-table-{{ $table->id }}"
                transform="rotate({{ $table->rotation }} {{ $cx }} {{ $cy }})"
                role="button"
                aria-label="{{ __('booking.table.aria', ['number' => $table->number, 'seats' => trans_choice('booking.table.seats', $table->seats), 'state' => $stateLabel]) }}"
                @if ($isFree || $isSelected)
                    tabindex="0"
                    data-choice
                    aria-pressed="{{ json_encode($isSelected) }}"
                    wire:click="selectTable({{ $table->id }})"
                    x-on:keydown.enter.prevent="$wire.selectTable({{ $table->id }})"
                    x-on:keydown.space.prevent="$wire.selectTable({{ $table->id }})"
                    {{-- Рамка фокуса браузера на <g> — прямокутник по межах фігури, що стирчить з-під заокруглених кутів.
                         Натомість фокус із клавіатури показує товстіший контур самої фігури (group-focus-visible нижче). --}}
                    class="group cursor-pointer outline-none"
                @else
                    aria-disabled="true"
                    class="cursor-not-allowed"
                @endif
            >
                @if ($table->shape === TableShape::Round)
                    <ellipse cx="{{ $cx }}" cy="{{ $cy }}" rx="{{ $table->width / 2 }}" ry="{{ $table->height / 2 }}" stroke-width="3" class="{{ $shapeClass }} transition-colors duration-200 group-focus-visible:[stroke-width:7]" />
                @else
                    <rect x="{{ $table->x }}" y="{{ $table->y }}" width="{{ $table->width }}" height="{{ $table->height }}" rx="14" stroke-width="3" class="{{ $shapeClass }} transition-colors duration-200 group-focus-visible:[stroke-width:7]" />
                @endif

                {{-- Текст не повертається разом зі столиком, щоб лишатися читабельним --}}
                <g transform="rotate({{ -$table->rotation }} {{ $cx }} {{ $cy }})" class="{{ $textClass }} pointer-events-none select-none" text-anchor="middle">
                    <text x="{{ $cx }}" y="{{ $cy - 2 }}" font-size="30" font-weight="700">{{ $table->number }}</text>
                    <text x="{{ $cx }}" y="{{ $cy + 24 }}" font-size="18">{{ trans_choice('booking.table.seats', $table->seats) }}</text>
                </g>
            </g>
        @endforeach
    </svg>
</div>
