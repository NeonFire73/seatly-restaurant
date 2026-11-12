<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        // Власник не може сам собі зняти роль чи вимкнути вхід, інакше заклад лишиться без власника
        $isSelf = fn (?User $record): bool => $record !== null && $record->is(auth()->user());

        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label("Ім'я")
                    ->required()
                    ->maxLength(100),
                TextInput::make('email')
                    ->label('E-mail для входу')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('role')
                    ->label('Роль')
                    ->options(UserRole::class)
                    ->required()
                    ->disabled($isSelf),
                TextInput::make('password')
                    ->label('Пароль')
                    ->password()
                    ->revealable()
                    ->rule(Password::defaults())
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Залиште порожнім, щоб не змінювати.' : null),
                Toggle::make('is_active')
                    ->label('Може входити в панель')
                    ->default(true)
                    ->disabled($isSelf),
            ]);
    }
}
