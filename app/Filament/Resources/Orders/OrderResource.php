<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\LocalTime;
use App\Support\Money;
use BackedEnum;
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
 * Розділ «Замовлення» (частина 2, п. 7.1) — лише перегляд: історія й рахунки.
 * Змінюються замовлення діями на сторінці «Зал».
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    // В українській з великої літери пишеться лише перше слово: «Блоки сторінок», а не «Блоки Сторінок»
    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'замовлення';

    protected static ?string $pluralModelLabel = 'Замовлення';

    public static function getRecordTitle(?Model $record): string
    {
        if ($record instanceof Order) {
            return "Замовлення № {$record->number}";
        }

        return 'Замовлення';
    }

    /**
     * Офіціант бачить лише замовлення, які обслуговував сам.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user instanceof User && $user->hasRole(UserRole::Waiter)) {
            $query->where('waiter_id', $user->id);
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['table', 'waiter']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label('№')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('opened_at')
                    ->label('Відкрито')
                    ->dateTime('d.m.Y H:i', LocalTime::zone())
                    ->sortable(),
                TextColumn::make('table.number')
                    ->label('Столик')
                    ->prefix('№'),
                TextColumn::make('waiter.name')
                    ->label('Офіціант')
                    ->placeholder('не призначено'),
                TextColumn::make('type')
                    ->label('Тип')
                    ->color('gray'),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge(),
                TextColumn::make('subtotal')
                    ->label('Сума')
                    ->formatStateUsing(fn (int $state) => Money::format($state, 'uk'))
                    ->alignEnd(),
                TextColumn::make('paid_total')
                    ->label('До сплати')
                    ->state(fn (Order $record) => Money::format($record->amountDue(), 'uk'))
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(OrderStatus::class),
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
                Section::make('Замовлення')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('number')->label('Номер')->fontFamily('mono'),
                        TextEntry::make('status')->label('Статус')->badge(),
                        TextEntry::make('type')->label('Тип'),
                        TextEntry::make('table.number')->label('Столик')->prefix('№'),
                        TextEntry::make('waiter.name')->label('Офіціант')->placeholder('не призначено'),
                        TextEntry::make('reservation.code')->label('Бронювання')->placeholder('без бронювання'),
                        TextEntry::make('opened_at')->label('Відкрито')->dateTime('d.m.Y H:i', LocalTime::zone()),
                        TextEntry::make('closed_at')->label('Закрито')->dateTime('d.m.Y H:i', LocalTime::zone())->placeholder('ще відкрите'),
                        TextEntry::make('comment')->label('Коментар')->placeholder('немає'),
                    ]),
                Section::make('Рахунок')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label('Сума')
                            ->formatStateUsing(fn (int $state) => Money::format($state, 'uk')),
                        TextEntry::make('paid_total')
                            ->label('Сплачено')
                            ->formatStateUsing(fn (int $state) => Money::format($state, 'uk')),
                        TextEntry::make('due')
                            ->label('До сплати')
                            ->state(fn (Order $record) => Money::format($record->amountDue(), 'uk'))
                            ->weight('bold'),
                    ]),
                Section::make('Позиції')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('name')->label('Страва')->weight('medium'),
                                TextEntry::make('quantity')->label('Кількість'),
                                TextEntry::make('total')
                                    ->label('Сума')
                                    ->state(fn (OrderItem $record) => Money::format($record->total(), 'uk')),
                                TextEntry::make('status')->label('Статус')->badge(),
                            ]),
                    ]),
                Section::make('Платежі')
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->hiddenLabel()
                            ->columns(5)
                            ->placeholder('Платежів ще не було')
                            ->schema([
                                TextEntry::make('created_at')->label('Створено')->dateTime('d.m.Y H:i', LocalTime::zone()),
                                TextEntry::make('method')->label('Спосіб'),
                                TextEntry::make('amount')
                                    ->label('Сума')
                                    ->formatStateUsing(fn (int $state) => Money::format($state, 'uk')),
                                TextEntry::make('tip_amount')
                                    ->label('Чайові')
                                    ->formatStateUsing(fn (int $state) => Money::format($state, 'uk')),
                                TextEntry::make('status')->label('Статус')->badge(),
                            ]),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
