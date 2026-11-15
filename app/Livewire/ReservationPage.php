<?php

namespace App\Livewire;

use App\Actions\Orders\CreatePreorder;
use App\Actions\Payments\StartReservationPayment;
use App\Actions\Reservations\CancelReservation;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\Booking\BookingException;
use App\Exceptions\Orders\OrderException;
use App\Exceptions\Payments\PaymentException;
use App\Livewire\Concerns\HasPreorderCart;
use App\Livewire\Concerns\KeepsPageLocale;
use App\Models\Order;
use App\Models\Reservation;
use App\Services\Booking\BookingSettings;
use App\Services\Booking\RefundPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Сторінка бронювання гостя (частина 1, п. 5.4): статус, попереднє замовлення, оплата, скасування.
 *
 * @property-read Reservation|null $reservation
 * @property-read Order|null $preorder
 */
class ReservationPage extends Component
{
    use HasPreorderCart, KeepsPageLocale;

    #[Locked]
    public string $token = '';

    public string $notice = '';

    // Гість збирає попереднє замовлення до вже створеного бронювання
    public bool $addingPreorder = false;

    /** @var array<int, int> кошик попереднього замовлення: ідентифікатор страви => кількість */
    public array $cart = [];

    public function mount(string $token): void
    {
        $this->token = $token;

        abort_if($this->reservation === null, 404);
    }

    #[Computed]
    public function reservation(): ?Reservation
    {
        $reservation = Reservation::findByToken($this->token);
        $reservation?->load(['table.hall', 'payments']);

        return $reservation;
    }

    #[Computed]
    public function preorder(): ?Order
    {
        return $this->reservation?->preorder()->with('items')->first();
    }

    /**
     * Скільки зараз треба заплатити: плата за неоплачене бронювання плюс неоплачене попереднє замовлення.
     */
    public function amountDue(): int
    {
        $reservation = $this->reservation;

        if ($reservation === null) {
            return 0;
        }

        $due = 0;

        if ($reservation->status === ReservationStatus::PendingPayment) {
            $due += $reservation->fee_amount;
        } elseif ($reservation->status !== ReservationStatus::Confirmed) {
            return 0;
        }

        if ($this->preorder !== null && $this->preorder->status === OrderStatus::AwaitingPayment) {
            $due += $this->preorder->subtotal;
        }

        return $due;
    }

    /**
     * Додати (або зібрати заново неоплачене) попереднє замовлення можна до підтвердженого бронювання,
     * поки до візиту лишається досить часу (частина 2, п. 3.1).
     */
    public function canAddPreorder(): bool
    {
        $reservation = $this->reservation;

        if ($reservation === null || $reservation->status !== ReservationStatus::Confirmed) {
            return false;
        }

        if ($this->preorder !== null && $this->preorder->status !== OrderStatus::AwaitingPayment) {
            return false;
        }

        return $reservation->minutesUntilStart() >= BookingSettings::preorderMinLead() && $this->preorderMenu->isNotEmpty();
    }

    public function startPreorder(): void
    {
        $this->notice = '';

        if (! $this->canAddPreorder()) {
            return;
        }

        $this->cart = [];

        // Неоплачене замовлення відкривається для змін із тим, що гість уже обрав
        foreach ($this->preorder->items ?? [] as $item) {
            $this->cart[$item->dish_id] = $item->quantity;
        }

        $this->cart = $this->cartItems();
        $this->addingPreorder = true;
    }

    public function cancelPreorderEdit(): void
    {
        $this->addingPreorder = false;
        $this->cart = [];
    }

    public function submitPreorder(CreatePreorder $createPreorder, StartReservationPayment $startPayment): void
    {
        $this->notice = '';
        $reservation = $this->reservation;

        abort_if($reservation === null, 404);

        if ($this->cartLines === []) {
            $this->notice = __('booking.preorder.errors.empty');

            return;
        }

        if ($this->tooManyPaymentAttempts()) {
            return;
        }

        try {
            $createPreorder->handle($reservation, $this->cartItems());
        } catch (OrderException $e) {
            unset($this->preorderMenu, $this->cartLines, $this->preorder);
            $this->notice = $e->getMessage();

            return;
        }

        $this->addingPreorder = false;
        $this->cart = [];
        unset($this->preorder, $this->reservation);

        $this->startPayment($startPayment);
    }

    public function pay(StartReservationPayment $startPayment): void
    {
        $this->notice = '';

        if ($this->tooManyPaymentAttempts()) {
            return;
        }

        $this->startPayment($startPayment);
    }

    public function cancel(CancelReservation $cancelReservation): void
    {
        $reservation = $this->reservation;

        abort_if($reservation === null, 404);

        if (! $reservation->isCancellableByGuest()) {
            $this->notice = __('booking.page.cannot_cancel');

            return;
        }

        try {
            $cancelReservation->handle($reservation);
        } catch (BookingException $e) {
            $this->notice = __('booking.page.cannot_cancel');

            // Гість саме платить: скасувати не можна з іншої причини, і її варто назвати
            if ($e->getMessage() === __('booking.page.payment_in_progress')) {
                $this->notice = $e->getMessage();
            }

            return;
        }

        $this->addingPreorder = false;
        unset($this->reservation, $this->preorder);
        $this->notice = __('booking.page.cancelled');
    }

    /**
     * Викликає таймер у браузері, коли час на оплату вийшов: сторінка перечитує статус бронювання.
     */
    public function holdExpired(): void
    {
        unset($this->reservation, $this->preorder);
    }

    public function render(): View
    {
        $reservation = $this->reservation;
        $refund = ['paid' => 0, 'refund' => 0];

        if ($reservation !== null && $reservation->isCancellableByGuest()) {
            $refund = RefundPolicy::summary($reservation);
        }

        return view('livewire.reservation-page', [
            'refund' => $refund,
            'preorderLeadLabel' => BookingWizard::humanMinutes(BookingSettings::preorderMinLead()),
            'preorderCancelLabel' => BookingWizard::humanMinutes(BookingSettings::preorderFreeCancel()),
        ]);
    }

    private function startPayment(StartReservationPayment $startPayment): void
    {
        $reservation = $this->reservation;

        abort_if($reservation === null, 404);

        try {
            $payment = $startPayment->handle($reservation, app()->getLocale());
        } catch (PaymentException $e) {
            unset($this->reservation, $this->preorder);
            $this->notice = $e->getMessage();

            return;
        }

        RateLimiter::hit($this->limiterKey(), 60);
        $this->redirect((string) $payment->page_url);
    }

    private function tooManyPaymentAttempts(): bool
    {
        if (RateLimiter::tooManyAttempts($this->limiterKey(), 6)) {
            $this->notice = __('payment.errors.too_many');

            return true;
        }

        return false;
    }

    private function limiterKey(): string
    {
        return 'reservation-pay:'.sha1($this->token).':'.request()->ip();
    }
}
