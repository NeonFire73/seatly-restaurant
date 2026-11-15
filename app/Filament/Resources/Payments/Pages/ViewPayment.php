<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Actions\Payments\RefundPayment;
use App\Actions\Payments\SyncPaymentStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\Payments\GatewayException;
use App\Exceptions\Payments\PaymentException;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Картка платежу (частина 2, п. 7.7). Самі зміни виконують класи з app/Actions; тут лише кнопки, права й повідомлення.
 */
class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Оновити статус у провайдера')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (Payment $record) => $record->isOnline() && $record->provider_invoice_id !== null)
                ->action(function (Payment $record): void {
                    try {
                        app(SyncPaymentStatus::class)->handle($record);
                    } catch (GatewayException $e) {
                        Notification::make()->title('Провайдер не відповів')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Статус: '.mb_strtolower($record->status->getLabel()))->success()->send();
                }),
            Action::make('refund')
                ->label('Повернути кошти')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('danger')
                ->modalHeading('Повернути кошти?')
                ->modalDescription(fn (Payment $record) => self::refundDescription($record))
                ->modalSubmitActionLabel('Повернути кошти')
                ->schema(fn (Payment $record) => [
                    // Вибір є лише там, де в платежі дві частини й жодної ще не повернено повністю
                    Select::make('part')
                        ->label('Що повернути')
                        ->options(self::refundParts($record))
                        ->default(RefundPayment::ALL)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->visible(count(self::refundParts($record)) > 1),
                    Textarea::make('reason')
                        ->label('Причина')
                        ->required()
                        ->maxLength(255)
                        ->rows(2),
                ])
                ->visible(fn (Payment $record) => $record->refundable() > 0 && Gate::allows('refund', $record))
                ->action(function (Payment $record, array $data): void {
                    /** @var User $user */
                    $user = auth()->user();

                    try {
                        app(RefundPayment::class)->handle($record, $user, $data['reason'], $data['part'] ?? RefundPayment::ALL);
                    } catch (PaymentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }

                    if ($record->status === PaymentStatus::Refunded || $record->refunded_amount > 0) {
                        Notification::make()->title('Кошти повернено')->success()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Повернення прийнято в обробку')
                        ->body('Банк ще виконує повернення: статус зміниться автоматично.')
                        ->warning()
                        ->send();
                }),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function refundParts(Payment $payment): array
    {
        $fee = $payment->booking_fee_amount - $payment->refundedFee();
        $order = $payment->amount - $payment->refundedOrderAmount();

        if ($fee === 0 || $order === 0) {
            return [RefundPayment::ALL => 'Усе, що лишилося: '.Money::format($payment->refundable(), 'uk')];
        }

        return [
            RefundPayment::ALL => 'Усе: '.Money::format($payment->refundable(), 'uk'),
            RefundPayment::FEE => 'Лише плату за бронювання: '.Money::format($fee, 'uk'),
            RefundPayment::ORDER => 'Лише суму страв: '.Money::format($order, 'uk'),
        ];
    }

    private static function refundDescription(Payment $payment): string
    {
        if ($payment->reservation_id !== null) {
            return 'Гостеві можна повернути до '.Money::format($payment->refundable(), 'uk').'. Скасувати повернення не можна.';
        }

        return 'Гостеві повернеться '.Money::format($payment->refundable(), 'uk')
            .' разом із чайовими, а сума знову стане до сплати за замовленням. Скасувати повернення не можна.';
    }
}
