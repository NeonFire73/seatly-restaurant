<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Дашборд (частина 2, пп. 7.1 і 7.8). Власник і менеджер бачать усі віджети, офіціант — лише бронювання
 * й зайнятість залу (це вирішує canView() кожного віджета). Кухні дашборд не потрібен: її екран — «Кухня».
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Дашборд';

    public static function shouldRegisterNavigation(): bool
    {
        return ! self::isKitchen();
    }

    public function mount(): void
    {
        // Головна адреса панелі — це і є дашборд, тож кухню звідси переводимо на її робочий екран, а не показуємо 403
        if (self::isKitchen()) {
            $this->redirect(KitchenBoard::getUrl());
        }
    }

    public function getColumns(): int|array
    {
        return 2;
    }

    private static function isKitchen(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasRole(UserRole::Kitchen);
    }
}
