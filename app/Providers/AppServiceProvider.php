<?php

namespace App\Providers;

use App\Models\Recipient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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
        // Per-purpose rate limiters, each with its own IP-keyed bucket so the
        // public returning-participant lookup, its server-side mobile
        // verification, and the recipient claim flow throttle independently
        // (an inline `throttle:N,1` would share one domain+IP bucket for guests
        // and let these endpoints interfere with each other).
        RateLimiter::for('participant-search', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('participant-verify', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('recipient-claim', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

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

        View::composer('admin.partials.action-menu', function ($view): void {
            $user = auth()->user();
            $menuRecipient = null;
            if ($user) {
                $menuRecipient = Recipient::where('user_id', $user->id)->first();
            }
            $view->with('menuRecipient', $menuRecipient);
        });
    }
}
