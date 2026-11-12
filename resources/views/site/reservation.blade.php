{{-- Сторінка бронювання гостя: доступ лише за посиланням із токеном, закрита від індексації (частина 1, п. 5.4) --}}
<x-layouts::app :title="__('booking.page.title', ['code' => $reservation->code])" noindex>
    <div class="container-site max-w-2xl pb-6 pt-12">
        <h1 class="text-4xl font-bold tracking-tight">{{ __('booking.page.heading') }}</h1>
    </div>

    <livewire:reservation-page :token="$reservation->token" />
</x-layouts::app>
