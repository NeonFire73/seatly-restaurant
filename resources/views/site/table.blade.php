{{-- Сторінка столика: відкривається за QR-кодом, закрита від індексації (частина 1, п. 5.5) --}}
<x-layouts::table :number="$table->number">
    <livewire:table-page :token="$table->qr_token" />
</x-layouts::table>
