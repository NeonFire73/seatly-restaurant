@php
    $dish = \App\Models\Dish::with('category')->where('is_popular', true)->first();
    $unavailable = (new \App\Models\Dish)->forceFill([
        'name' => ['uk' => 'Страва, якої немає'],
        'description' => ['uk' => 'Так виглядає картка, коли кухня вимкнула «є в наявності».'],
        'price' => 19900,
        'is_available' => false,
        'tags' => [],
    ]);
@endphp

{{-- Внутрішня сторінка з компонентами (частина 1, п. 3.4); маршрут існує лише в локальному середовищі --}}
<x-layouts::app title="UI-кіт">
    <div class="container-site space-y-14 py-12">
        <h1 class="text-4xl font-bold tracking-tight">UI-кіт</h1>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Кольори</h2>
            <div class="flex flex-wrap gap-3 text-xs">
                @foreach (['surface', 'muted', 'ink', 'ink-soft', 'line', 'brand', 'success', 'danger', 'warning'] as $color)
                    <div class="w-28">
                        {{-- Колір через змінну теми: динамічний клас bg-… Tailwind не знайшов би під час збірки --}}
                        <div class="h-16 rounded-card ring-1 ring-line" style="background: var(--color-{{ $color }})"></div>
                        <p class="mt-1">{{ $color }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Кнопки</h2>
            <div class="flex flex-wrap items-center gap-3">
                <x-button>Основна</x-button>
                <x-button variant="secondary">Другорядна</x-button>
                <x-button variant="text">Текстова</x-button>
                <x-button disabled>Вимкнена</x-button>
                <x-button size="lg"><x-site.icon name="calendar" class="size-5" />Велика з іконкою</x-button>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Позначки</h2>
            <div class="flex flex-wrap gap-2">
                <x-badge>Нейтральна</x-badge>
                <x-badge tone="outline">Вегетаріанське</x-badge>
                <x-badge tone="brand">Популярне</x-badge>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Картки страв</h2>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @if ($dish)
                    <x-dish-card :dish="$dish" />
                @endif
                <x-dish-card :dish="$unavailable" />
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Типографіка тексту з редактора</h2>
            <div class="prose-content max-w-2xl">
                <h2>Заголовок другого рівня</h2>
                <p>Абзац із <a href="#">посиланням</a> і <strong>жирним</strong> текстом.</p>
                <ul><li>Перший пункт</li><li>Другий пункт</li></ul>
                <table><thead><tr><th>Колонка</th><th>Значення</th></tr></thead><tbody><tr><td>Рядок</td><td>1</td></tr></tbody></table>
            </div>
        </section>
    </div>
</x-layouts::app>
