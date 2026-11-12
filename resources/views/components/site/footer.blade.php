@php
    use App\Services\SiteContent;

    $contacts = SiteContent::contacts();
    $socials = array_filter($contacts['socials']);
@endphp

<footer class="mt-24 border-t border-line bg-muted">
    <div class="container-site grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div class="space-y-3">
            <x-site.logo />
            <p class="text-sm text-ink-soft">{{ __('site.tagline') }}</p>
        </div>

        <nav aria-label="{{ __('site.nav.main') }}">
            <ul class="space-y-2 text-sm">
                <li><a href="{{ locale_route('menu') }}" class="hover:underline">{{ __('site.nav.menu') }}</a></li>
                <li><a href="{{ locale_route('booking') }}" class="hover:underline">{{ __('site.nav.book') }}</a></li>
                <li><a href="{{ locale_route('about') }}" class="hover:underline">{{ __('site.nav.about') }}</a></li>
                <li><a href="{{ locale_route('contacts') }}" class="hover:underline">{{ __('site.nav.contacts') }}</a></li>
            </ul>
        </nav>

        <address class="space-y-2 text-sm not-italic">
            @if (SiteContent::address())
                <p class="flex gap-2"><x-site.icon name="map-pin" class="size-5 shrink-0" />{{ SiteContent::address() }}</p>
            @endif
            @if ($contacts['phone'])
                <p class="flex gap-2"><x-site.icon name="phone" class="size-5 shrink-0" /><a href="tel:{{ preg_replace('/[^\d+]/', '', $contacts['phone']) }}" class="hover:underline">{{ $contacts['phone'] }}</a></p>
            @endif
            @if ($contacts['email'])
                <p class="flex gap-2"><x-site.icon name="mail" class="size-5 shrink-0" /><a href="mailto:{{ $contacts['email'] }}" class="hover:underline">{{ $contacts['email'] }}</a></p>
            @endif
        </address>

        @if ($socials)
            <div>
                <p class="mb-2 text-sm font-semibold">{{ __('site.contacts.socials') }}</p>
                <ul class="flex gap-1">
                    @foreach ($socials as $network => $url)
                        <li>
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="inline-flex size-11 items-center justify-center rounded-full hover:bg-surface">
                                <x-site.icon :name="$network" />
                                <span class="sr-only">{{ ucfirst($network) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="border-t border-line">
        <div class="container-site flex flex-col gap-3 py-6 text-xs text-ink-soft sm:flex-row sm:items-center sm:justify-between">
            <p>{{ __('site.demo_notice') }}</p>
            <ul class="flex gap-4">
                <li><a href="{{ locale_route('offer') }}" class="hover:underline">{{ __('site.footer.offer') }}</a></li>
                <li><a href="{{ locale_route('privacy') }}" class="hover:underline">{{ __('site.footer.privacy') }}</a></li>
            </ul>
        </div>
    </div>
</footer>
