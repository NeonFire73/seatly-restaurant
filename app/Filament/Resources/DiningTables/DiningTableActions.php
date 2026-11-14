<?php

namespace App\Filament\Resources\DiningTables;

use App\Models\DiningTable;
use App\Support\QrCode;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

/**
 * Дії з QR-кодами столиків (частина 2, п. 7.6).
 */
class DiningTableActions
{
    public static function showQr(): Action
    {
        return Action::make('showQr')
            ->label('Показати QR')
            ->icon(Heroicon::OutlinedQrCode)
            ->color('gray')
            ->modalHeading(fn (DiningTable $record) => "QR-код столика №{$record->number}")
            ->modalContent(fn (DiningTable $record) => view('filament.tables.qr', [
                'svg' => QrCode::svg($record->qrUrl()),
                'url' => $record->qrUrl(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Закрити');
    }

    public static function regenerateToken(): Action
    {
        return Action::make('regenerateToken')
            ->label('Перевипустити токен')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(fn (DiningTable $record) => "Перевипустити токен столика №{$record->number}?")
            ->modalDescription('Старий QR-код перестане працювати: табличку на столику доведеться надрукувати заново.')
            ->action(function (DiningTable $record): void {
                $record->regenerateQrToken();

                Notification::make()
                    ->title("Токен столика №{$record->number} перевипущено")
                    ->body('Надрукуйте нову табличку.')
                    ->success()
                    ->send();
            });
    }

    public static function printSelected(): BulkAction
    {
        return BulkAction::make('printQr')
            ->label('Друк табличок')
            ->icon(Heroicon::OutlinedPrinter)
            ->action(function (Collection $records, Component $livewire): void {
                $livewire->redirect(route('admin.tables.qr-print', ['ids' => implode(',', $records->modelKeys())]));
            });
    }

    public static function printAll(): Action
    {
        return Action::make('printAllQr')
            ->label('Друк табличок')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->url(route('admin.tables.qr-print'))
            ->openUrlInNewTab();
    }
}
