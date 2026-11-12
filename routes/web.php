<?php

use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\PageController;
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
    Route::get('/booking', [PageController::class, 'booking'])->name('booking');
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

if (app()->isLocal()) {
    Route::view('/ui-kit', 'site.ui-kit')->middleware(SetLocale::class.':uk')->name('ui-kit');
}
