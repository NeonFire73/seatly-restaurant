@php
    $contacts = \App\Services\SiteContent::contacts();
    $lat = $contacts['lat'];
    $lng = $contacts['lng'];
@endphp

@if ($lat !== null && $lng !== null)
    @php
        $bbox = implode(',', [$lng - 0.012, $lat - 0.005, $lng + 0.012, $lat + 0.005]);
    @endphp
    <div {{ $attributes->class('overflow-hidden rounded-card bg-muted') }}>
        {{-- OpenStreetMap не потребує ключа API й не ставить рекламних cookie --}}
        <iframe
            src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bbox }}&amp;layer=mapnik&amp;marker={{ $lat }},{{ $lng }}"
            title="{{ __('site.contacts.map_title') }}"
            loading="lazy"
            class="aspect-[4/3] w-full border-0 sm:aspect-[16/9]"
        ></iframe>
    </div>
    <a href="https://www.google.com/maps/search/?api=1&amp;query={{ $lat }},{{ $lng }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex min-h-11 items-center gap-2 text-sm font-medium underline-offset-4 hover:underline">
        <x-site.icon name="map-pin" class="size-4" />
        {{ __('site.contacts.open_map') }}
    </a>
@endif
