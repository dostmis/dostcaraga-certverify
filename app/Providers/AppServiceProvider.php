<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        $host = request()->getHost();
        $isLocalHost = in_array($host, ['127.0.0.1', 'localhost', '0.0.0.0'], true);
        $appUrlScheme = parse_url((string) config('app.url'), PHP_URL_SCHEME);

        // Keep local built-in server access on HTTP, but force HTTPS for real domain traffic.
        if (! $isLocalHost && $appUrlScheme === 'https') {
            URL::forceScheme('https');
        }
    }
}
