<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
<<<<<<< HEAD
        if ($this->shouldForceHttps()) {
            URL::forceScheme('https');
        }
    }

    private function shouldForceHttps(): bool
    {
        if (! app()->environment('production')) {
            return false;
        }

        return Str::startsWith((string) config('app.url'), 'https://');
=======
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
>>>>>>> c5e8d13 (Improve certificate UI and add backup scripts)
    }
}
