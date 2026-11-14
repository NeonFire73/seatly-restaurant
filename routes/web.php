<?php

use App\Http\Controllers\Admin\QrPrintController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\ReservationController;
use App\Http\Controllers\Site\TableController;
use App\Http\Middleware\SetLocale;
use App\Support\Localization;
use Illuminate\Support\Facades\Route;

/*
 * Публічні сторінки реєструються для кожної мови: основна без префікса (`menu`),
 * інші — з префіксом в адресі й назві (`/en/menu`, `en.menu`). Адреси будує locale_route().
 */
$publicRoutes = function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::view('/menu', 'site.menu')->name('menu');
    Route::view('/booking', 'site.booking')->name('booking');
    Route::get('/booking/{token}', [ReservationController::class, 'show'])->name('reservation.show');
    Route::get('/booking/{token}/calendar.ics', [ReservationController::class, 'calendar'])->name('reservation.calendar');
    Route::get('/t/{token}', TableController::class)->name('table.show');
    Route::get('/about', [PageController::class, 'about'])->name('about');
    Route::get('/contacts', [PageController::class, 'contacts'])->name('contacts');
    Route::get('/privacy', [PageController::class, 'text'])->defaults('slug', 'privacy')->name('privacy');
    Route::get('/offer', [PageController::class, 'text'])->defaults('slug', 'offer')->name('offer');
};

foreach (Localization::locales() as $locale) {
    if ($locale === Localization::main()) {
        Route::middleware(SetLocale::class.':'.$locale)->group($publicRoutes);
    } else {
        Route::prefix($locale)->name("{$locale}.")->middleware(SetLocale::class.':'.$locale)->group($publicRoutes);
    }
}

// Друк табличок із QR-кодами — сторінка поза панеллю Filament; права перевіряє сам контролер
Route::get('/admin/tables/qr-print', QrPrintController::class)->name('admin.tables.qr-print');

if (app()->isLocal()) {
    Route::view('/ui-kit', 'site.ui-kit')->middleware(SetLocale::class.':uk')->name('ui-kit');
}
