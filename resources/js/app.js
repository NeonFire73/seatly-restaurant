// Alpine постачається разом із Livewire; тут лише дрібні компоненти, яким не потрібен запит на сервер.
document.addEventListener('alpine:init', () => {
    // Зворотний відлік утримання столика в майстрі бронювання. expiresAt — мітка часу в секундах.
    window.Alpine.data('holdTimer', (expiresAt) => ({
        label: '',
        timer: null,

        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
        },

        destroy() {
            clearInterval(this.timer);
        },

        tick() {
            const left = Math.max(0, expiresAt - Math.floor(Date.now() / 1000));
            const minutes = Math.floor(left / 60);
            const seconds = String(left % 60).padStart(2, '0');

            this.label = `${minutes}:${seconds}`;

            if (left === 0) {
                clearInterval(this.timer);
                this.$wire.holdExpired();
            }
        },
    }));

    // Маска телефона +380 XX XXX XX XX. Значення одразу передається в Livewire без окремого запиту.
    window.Alpine.data('phoneMask', () => ({
        display: '',

        init() {
            this.display = this.format(this.$wire.phone || '');
        },

        onInput() {
            this.display = this.format(this.display);
            this.$wire.phone = this.display;
        },

        format(value) {
            let digits = value.replace(/\D/g, '');

            if (digits.startsWith('380')) {
                digits = digits.slice(3);
            } else if (digits.startsWith('80')) {
                digits = digits.slice(2);
            } else if (digits.startsWith('0')) {
                digits = digits.slice(1);
            }

            digits = digits.slice(0, 9);

            if (digits === '') {
                return '';
            }

            const groups = [digits.slice(0, 2), digits.slice(2, 5), digits.slice(5, 7), digits.slice(7, 9)];

            return '+380 ' + groups.filter((group) => group !== '').join(' ');
        },
    }));
});
