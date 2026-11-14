<?php

namespace App\Livewire;

use App\Actions\Payments\SyncPaymentStatus;
use App\Exceptions\Payments\GatewayException;
use App\Livewire\Concerns\KeepsPageLocale;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Результат оплати (частина 1, п. 5.6). Сторінка показує статус із бази й опитує його, доки він не стане
 * кінцевим. Сам факт повернення гостя нічого не означає: статус змінюють лише дані провайдера (частина 2, п. 6.2).
 *
 * @property-read Payment|null $payment
 */
class PaymentResult extends Component
{
    use KeepsPageLocale;

    // Не частіше ніж раз на стільки секунд сторінка сама питає провайдера про статус
    private const SYNC_EVERY = 10;

    #[Locked]
    public string $paymentId = '';

    public function mount(string $paymentId, SyncPaymentStatus $syncStatus): void
    {
        $this->paymentId = $paymentId;

        abort_if($this->payment === null, 404);

        $this->refreshStatus($syncStatus);
    }

    #[Computed]
    public function payment(): ?Payment
    {
        return Payment::with('order.table')->find($this->paymentId);
    }

    /**
     * Вебхук міг ще не дійти (а без тунелю в розробці не дійде взагалі), тож статус запитується напряму.
     */
    public function refreshStatus(SyncPaymentStatus $syncStatus): void
    {
        $payment = $this->payment;

        if ($payment === null || ! $payment->status->isPending()) {
            return;
        }

        if (! Cache::add('payment-sync.'.$payment->id, true, self::SYNC_EVERY)) {
            return;
        }

        try {
            $syncStatus->handle($payment);
        } catch (GatewayException $e) {
            report($e);
        }

        unset($this->payment);
    }

    public function render(): View
    {
        return view('livewire.payment-result');
    }
}
