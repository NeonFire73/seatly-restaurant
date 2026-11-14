<?php

namespace App\Filament\Resources\PaymentWebhookLogs;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\PaymentWebhookLogs\Pages\ListPaymentWebhookLogs;
use App\Models\PaymentWebhookLog;
use App\Support\LocalTime;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Журнал вебхуків (частина 2, п. 7.1) — лише читання, лише для власника: що надсилав провайдер
 * і чи пройшов підпис.
 */
class PaymentWebhookLogResource extends Resource
{
    protected static ?string $model = PaymentWebhookLog::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'webhook-logs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = 'Керування';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'вебхук';

    protected static ?string $pluralModelLabel = 'Журнал вебхуків';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Отримано')
                    ->dateTime('d.m.Y H:i:s', LocalTime::zone())
                    ->sortable(),
                TextColumn::make('provider')
                    ->label('Провайдер'),
                TextColumn::make('provider_invoice_id')
                    ->label('Рахунок')
                    ->fontFamily('mono')
                    ->searchable()
                    ->placeholder('немає'),
                TextColumn::make('payload.status')
                    ->label('Статус')
                    ->placeholder('—'),
                TextColumn::make('signature_valid')
                    ->label('Підпис')
                    ->badge()
                    ->state(fn (PaymentWebhookLog $record) => PaymentResource::signatureLabel($record))
                    ->color(fn (PaymentWebhookLog $record) => PaymentResource::signatureColor($record)),
                TextColumn::make('processed_at')
                    ->label('Оброблено')
                    ->dateTime('d.m.Y H:i:s', LocalTime::zone())
                    ->placeholder('ні'),
                TextColumn::make('error')
                    ->label('Помилка')
                    ->placeholder('немає')
                    ->wrap(),
            ])
            ->filters([
                TernaryFilter::make('signature_valid')
                    ->label('Підпис')
                    ->trueLabel('Чинний')
                    ->falseLabel('Недійсний'),
            ])
            ->recordActions([
                ViewAction::make()->modalHeading('Вебхук'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextEntry::make('created_at')->label('Отримано')->dateTime('d.m.Y H:i:s', LocalTime::zone()),
                TextEntry::make('provider_invoice_id')->label('Рахунок')->fontFamily('mono')->placeholder('немає'),
                TextEntry::make('error')->label('Помилка')->placeholder('немає')->columnSpanFull(),
                TextEntry::make('payload')
                    ->label('Тіло запиту')
                    ->state(fn (PaymentWebhookLog $record) => json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                    ->fontFamily('mono')
                    ->extraAttributes(['style' => 'white-space: pre-wrap; word-break: break-all;'])
                    ->columnSpanFull(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentWebhookLogs::route('/'),
        ];
    }
}
