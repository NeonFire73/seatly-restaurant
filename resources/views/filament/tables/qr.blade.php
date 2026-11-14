{{-- Вікно «Показати QR» у списку столиків. Тло коду біле в обох темах: на темному він не зчитується --}}
<div class="flex flex-col items-center gap-4 text-center">
    <div style="width: 14rem; padding: 0.75rem; background: #ffffff; border-radius: 0.75rem;">
        {!! $svg !!}
    </div>

    <p class="text-sm text-gray-600 dark:text-gray-400">
        Код веде на сторінку столика з меню й рахунком. Після перевипуску токена ця адреса перестане працювати.
    </p>

    <a href="{{ $url }}" target="_blank" rel="noopener" class="break-all text-sm font-medium text-primary-600 underline dark:text-primary-400">{{ $url }}</a>
</div>
