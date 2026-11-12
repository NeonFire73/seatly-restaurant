@php
    use App\Services\SiteContent;
    use Carbon\CarbonImmutable;

    $hours = SiteContent::workingHours();
    $monday = CarbonImmutable::now()->startOfWeek()->locale(app()->getLocale());
@endphp

<dl {{ $attributes->class('divide-y divide-line text-sm') }}>
    @foreach ($hours as $day)
        @php($isToday = now()->dayOfWeekIso === $day->weekday)
        <div @class(['flex justify-between gap-4 py-2', 'font-semibold' => $isToday])>
            <dt>{{ \Illuminate\Support\Str::ucfirst($monday->addDays($day->weekday - 1)->dayName) }}</dt>
            <dd>
                @if ($day->is_closed)
                    {{ __('site.contacts.closed') }}
                @else
                    {{ substr((string) $day->opens_at, 0, 5) }}–{{ substr((string) $day->closes_at, 0, 5) }}
                @endif
            </dd>
        </div>
    @endforeach
</dl>
