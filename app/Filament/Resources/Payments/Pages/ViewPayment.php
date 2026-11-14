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
                ->modalDescription(fn (Payment $record) => 'Гостеві повернеться '.Money::format($record->total(), 'uk')
                    .' разом із чайовими, а сума знову стане до сплати за замовленням. Скасувати повернення не можна.')
                ->modalSubmitActionLabel('Повернути кошти')
                ->schema([
                    Textarea::make('reason')
                        ->label('Причина')
                        ->required()
                        ->maxLength(255)
                        ->rows(2),
                ])
                ->visible(fn (Payment $record) => $record->status === PaymentStatus::Success && Gate::allows('refund', $record))
                ->action(function (Payment $record, array $data): void {
                    /** @var User $user */
                    $user = auth()->user();

                    try {
                        app(RefundPayment::class)->handle($record, $user, $data['reason']);
                    } catch (PaymentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }

                    if ($record->status === PaymentStatus::Refunded) {
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
}
