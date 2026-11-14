{{-- Результат оплати: відкривається після повернення з платіжної сторінки, закрита від індексації (частина 1, п. 5.6) --}}
<x-layouts::app :title="__('payment.title')" noindex>
    <livewire:payment-result :payment-id="$payment->id" />
</x-layouts::app>
