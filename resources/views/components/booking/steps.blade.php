@props([
    // Назви кроків за порядком
    'steps',
    'current' => 1,
])

{{-- Індикатор кроків майстра. Стан передається не лише кольором: пройдений крок має позначку, поточний — aria-current --}}
<nav aria-label="{{ __('booking.steps.label') }}" {{ $attributes }}>
    <p class="text-sm text-ink-soft sm:hidden">
        {{ __('booking.steps.of', ['current' => $current, 'total' => count($steps)]) }}:
        <span class="font-semibold text-ink">{{ $steps[$current - 1] }}</span>
    </p>

    <ol class="flex items-center gap-2 max-sm:mt-2">
        @foreach ($steps as $index => $label)
            @php($number = $index + 1)
            <li
                @if ($number === $current) aria-current="step" @endif
                @class(['flex items-center gap-2', 'flex-1' => ! $loop->last])
            >
                <span @class([
                    'flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold transition-colors duration-200',
                    'bg-brand text-on-brand' => $number === $current,
                    'bg-ink text-surface' => $number < $current,
                    'border border-line text-ink-soft' => $number > $current,
                ])>
                    @if ($number < $current)
                        <x-site.icon name="check" class="size-4" />
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span @class(['hidden text-sm sm:inline', 'font-semibold' => $number === $current, 'text-ink-soft' => $number > $current])>{{ $label }}</span>
                @if (! $loop->last)
                    <span @class(['h-px flex-1', 'bg-ink' => $number < $current, 'bg-line' => $number >= $current]) aria-hidden="true"></span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
