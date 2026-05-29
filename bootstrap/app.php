<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(function () {
                require base_path('routes/recipient.php');
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES'));
        $middleware->validateCsrfTokens(except: [
            'webhooks/telegram/*',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminOnly::class,
            'role' => \App\Http\Middleware\RoleIn::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

$localStoragePath = getenv('LARAVEL_STORAGE_PATH')
    ?: ($_ENV['LARAVEL_STORAGE_PATH'] ?? null)
    ?: ($_SERVER['LARAVEL_STORAGE_PATH'] ?? null);

if (is_string($localStoragePath) && $localStoragePath !== '') {
    $app->useStoragePath($localStoragePath);
}

return $app;
