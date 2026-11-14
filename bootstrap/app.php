<?php

use App\Support\Localization;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->preventRequestForgery(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Сторінки помилок мають бути мовою адреси, а SetLocale для неіснуючого маршруту не виконується
        $exceptions->render(function (Throwable $e, Request $request) {
            Localization::applyFromPath($request);

            return null;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
