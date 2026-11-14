<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Support\LocalTime;
use App\Support\Money;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Розділ «Платежі» (частина 2, п. 7.7) — лише читання плюс дії «Оновити статус» і «Повернути кошти».
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    // В українській з великої літери пишеться лише перше слово: «Блоки сторінок», а не «Блоки Сторінок»
    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'платежу';

    protected static ?string $pluralModelLabel = 'Платежі';

    public static function getRecordTitle(?Model $record): string
    {
        if ($record instanceof Payment) {
            return 'Платіж на '.Money::format($record->total(), 'uk');
        }

        return 'Платіж';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['order.table', 'waiter']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime('d.m.Y H:i', LocalTime::zone())
                    ->sortable(),
                TextColumn::make('order.number')
                    ->label('Замовлення')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('order.table.number')
                    ->label('Столик')
                    ->prefix('№'),
                TextColumn::make('amount')
                    ->label('Сума')
                    ->formatStateUsing(fn (int $state) => Money::format($state, 'uk'))
                    ->alignEnd(),
                TextColumn::make('tip_amount')
                    ->label('Чайові')
                    ->formatStateUsing(fn (int $state) => Money::format($state, 'uk'))
                    ->alignEnd(),
                TextColumn::make('method')
                    ->label('Спосіб')
                    ->color('gray'),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge(),
                TextColumn::make('waiter.name')
                    ->label('Офіціант')
                    ->placeholder('не призначено')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(PaymentStatus::class),
                SelectFilter::make('method')
                    ->label('Спосіб')
                    ->options(PaymentMethod::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Платіж')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')->label('Статус')->badge(),
                        TextEntry::make('method')->label('Спосіб'),
                        TextEntry::make('provider')->label('Провайдер')->placeholder('без провайдера'),
                        TextEntry::make('amount')
                            ->label('За рахунком')
                            ->formatStateUsing(fn (int $state) => Money::format($state, 'uk')),
                        TextEntry::make('tip_amount')
                            ->label('Чайові')
                            ->formatStateUsing(fn (int $state) => Money::format($state, 'uk')),
                        TextEntry::make('total')
                            ->label('Разом')
                            ->state(fn (Payment $record) => Money::format($record->total(), 'uk'))
                            ->weight('bold'),
                        TextEntry::make('order.number')->label('Замовлення')->fontFamily('mono'),
                        TextEntry::make('order.table.number')->label('Столик')->prefix('№'),
                        TextEntry::make('waiter.name')->label('Отримувач чайових')->placeholder('не призначено'),
                        TextEntry::make('created_at')->label('Створено')->dateTime('d.m.Y H:i:s', LocalTime::zone()),
                        TextEntry::make('paid_at')->label('Сплачено')->dateTime('d.m.Y H:i:s', LocalTime::zone())->placeholder('не сплачено'),
                        TextEntry::make('recorder.name')->label('Прийняв оплату')->placeholder('гість онлайн'),
                        TextEntry::make('provider_invoice_id')->label('Рахунок у провайдера')->fontFamily('mono')->placeholder('немає')->copyable(),
                        TextEntry::make('id')->label('Ідентифікатор платежу')->fontFamily('mono')->copyable(),
                        TextEntry::make('failure_reason')->label('Причина відмови')->placeholder('немає'),
                    ]),
                Section::make('Повернення коштів')
                    ->columns(3)
                    ->visible(fn (Payment $record) => $record->refunded_amount > 0 || isset($record->meta['refund']))
                    ->schema([
                        TextEntry::make('refunded_amount')
                            ->label('Повернено')
                            ->formatStateUsing(fn (int $state) => Money::format($state, 'uk')),
                        TextEntry::make('refunded_at')->label('Коли')->dateTime('d.m.Y H:i', LocalTime::zone())->placeholder('ще обробляється'),
                        TextEntry::make('meta.refund.by_name')->label('Хто повернув')->placeholder('автоматично'),
                        TextEntry::make('meta.refund.reason')->label('Причина')->placeholder('не вказано')->columnSpanFull(),
                    ]),
                Section::make('Історія статусів')
                    ->schema([
                        TextEntry::make('history')
                            ->hiddenLabel()
                            ->state(fn (Payment $record) => self::historyLines($record))
                            ->listWithLineBreaks()
                            ->placeholder('Офлайн-платіж: історії немає'),
                    ]),
                Section::make('Вебхуки')
                    ->visible(fn (Payment $record) => $record->provider_invoice_id !== null)
                    ->schema([
                        RepeatableEntry::make('webhookLogs')
                            ->hiddenLabel()
                            ->columns(4)
                            ->placeholder('Вебхуків за цим платежем не було')
                            ->schema([
                                TextEntry::make('created_at')->label('Отримано')->dateTime('d.m.Y H:i:s', LocalTime::zone()),
                                TextEntry::make('payload.status')->label('Статус у провайдера')->placeholder('—'),
                                TextEntry::make('signature_valid')
                                    ->label('Підпис')
                                    ->badge()
                                    ->state(fn (PaymentWebhookLog $record) => self::signatureLabel($record))
                                    ->color(fn (PaymentWebhookLog $record) => self::signatureColor($record)),
                                TextEntry::make('error')->label('Помилка')->placeholder('немає'),
                            ]),
                    ]),
            ]);
    }

    public static function signatureLabel(PaymentWebhookLog $log): string
    {
        if ($log->signature_valid) {
            return 'чинний';
        }

        return 'недійсний';
    }

    public static function signatureColor(PaymentWebhookLog $log): string
    {
        if ($log->signature_valid) {
            return 'success';
        }

        return 'danger';
    }

    /**
     * @return list<string>
     */
    private static function historyLines(Payment $payment): array
    {
        $lines = [];

        foreach ($payment->history() as $entry) {
            $status = PaymentStatus::tryFrom($entry['status']);
            $label = $entry['status'];

            if ($status !== null) {
                $label = $status->getLabel();
            }

            $lines[] = LocalTime::of(CarbonImmutable::parse($entry['at']))->format('d.m.Y H:i:s').' — '.$label;
        }

        return $lines;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
