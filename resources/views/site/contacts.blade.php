@php
    use App\Services\SiteContent;

    $contacts = SiteContent::contacts();
    $socials = array_filter($contacts['socials']);
@endphp

<x-layouts::app seo="contacts">
    <div class="container-site grid gap-12 pt-12 lg:grid-cols-[1fr_1.4fr]">
        <div>
            <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">{{ __('site.nav.contacts') }}</h1>

            <dl class="mt-8 space-y-5">
                @if (SiteContent::address())
                    <div>
                        <dt class="text-sm font-semibold text-ink-soft">{{ __('site.contacts.address') }}</dt>
                        <dd class="mt-1 text-lg">{{ SiteContent::address() }}</dd>
                    </div>
                @endif
                @if ($contacts['phone'])
                    <div>
                        <dt class="text-sm font-semibold text-ink-soft">{{ __('site.contacts.phone') }}</dt>
                        <dd class="mt-1 text-lg"><a href="tel:{{ preg_replace('/[^\d+]/', '', $contacts['phone']) }}" class="hover:underline">{{ $contacts['phone'] }}</a></dd>
                    </div>
                @endif
                @if ($contacts['email'])
                    <div>
                        <dt class="text-sm font-semibold text-ink-soft">{{ __('site.contacts.email') }}</dt>
                        <dd class="mt-1 text-lg"><a href="mailto:{{ $contacts['email'] }}" class="hover:underline">{{ $contacts['email'] }}</a></dd>
                    </div>
                @endif
            </dl>

            <h2 class="mt-10 text-xl font-semibold">{{ __('site.contacts.hours') }}</h2>
            <x-site.hours class="mt-3 max-w-sm" />

            @if ($socials)
                <h2 class="mt-10 text-xl font-semibold">{{ __('site.contacts.socials') }}</h2>
                <ul class="mt-3 flex gap-2">
                    @foreach ($socials as $network => $url)
                        <li>
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-2 rounded-full border border-line px-4 text-sm font-medium hover:border-ink">
                                <x-site.icon :name="$network" class="size-4" />
                                {{ ucfirst($network) }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div>
            <x-site.map />
        </div>
    </div>
</x-layouts::app>
