{{-- Таблички з QR-кодами для друку: завжди світлі, незалежно від теми адмінки (частина 2, розділ 7) --}}
<!DOCTYPE html>
<html lang="uk">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>Таблички з QR-кодами — {{ config('seatly.brand') }}</title>
        @vite(['resources/css/app.css'])
        <style>
            @page { size: A4; margin: 10mm; }

            @media print {
                .no-print { display: none !important; }
                /* Без цього браузер може розрізати табличку між двома аркушами */
                .qr-card { break-inside: avoid; }
            }
        </style>
    </head>
    <body class="bg-surface font-sans text-ink antialiased">
        <div class="no-print flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight">Таблички з QR-кодами</h1>
                <p class="text-sm text-ink-soft">Столиків: {{ $cards->count() }}. Після перевипуску токена стару табличку треба замінити.</p>
            </div>
            <div class="flex gap-3">
                <x-button variant="secondary" :href="url('/admin/tables')">До столиків</x-button>
                <x-button onclick="window.print()">Друкувати</x-button>
            </div>
        </div>

        @if ($cards->isEmpty())
            <p class="p-6 text-ink-soft">Немає столиків для друку.</p>
        @endif

        <div class="grid grid-cols-2 gap-4 p-6 print:p-0">
            @foreach ($cards as $card)
                <section class="qr-card flex flex-col items-center gap-3 rounded-card border border-line p-6 text-center">
                    <p class="text-2xl font-bold tracking-tight">Seatly</p>
                    <div class="w-44 [&>svg]:size-full">{!! $card['svg'] !!}</div>
                    <p class="text-3xl font-bold">Столик № {{ $card['table']->number }}</p>
                    <p class="text-sm text-ink-soft">{{ $card['table']->hall->name }}</p>
                    <p class="text-sm leading-snug">
                        Наведіть камеру, щоб переглянути меню й рахунок<br>
                        <span lang="en" class="text-ink-soft">Scan to see the menu and your bill</span>
                    </p>
                </section>
            @endforeach
        </div>
    </body>
</html>
