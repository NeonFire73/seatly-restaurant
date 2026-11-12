<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label("Ім'я")
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->color('gray'),
                TextColumn::make('role')
                    ->label('Роль')
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Вхід дозволено')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Роль')
                    ->options(UserRole::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
