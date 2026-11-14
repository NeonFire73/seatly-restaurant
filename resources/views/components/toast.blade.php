{{-- Спливаюче сповіщення UI-кіта (частина 1, п. 3.4). Показується подією браузера `toast` з полем message;
     Livewire-компонент надсилає її через $this->dispatch('toast', message: '…'). Зникає саме за п'ять секунд. --}}
<div
    x-data="{
        message: '',
        visible: false,
        timer: null,
        show(text) {
            this.message = text;
            this.visible = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => { this.visible = false }, 5000);
        },
    }"
    x-on:toast.window="show($event.detail.message)"
    role="status"
    aria-live="polite"
    {{-- Сповіщення не перехоплює натискання: під ним можуть бути вкладки чи кнопки --}}
    {{ $attributes->class('pointer-events-none fixed inset-x-0 bottom-24 z-50 flex justify-center px-4') }}
>
    <p
        x-show="visible"
        x-cloak
        x-transition.opacity.duration.200ms
        x-text="message"
        class="max-w-sm rounded-card bg-ink px-4 py-3 text-center text-sm font-medium text-surface shadow-lg"
    ></p>
</div>
