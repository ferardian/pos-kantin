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
        if (str_starts_with(config('app.url'), 'https://') && !app()->runningInConsole()) {
            $host = request()->getHost();
            // Jangan paksa HTTPS jika diakses via IP Lokal (LAN) atau localhost
            if (!in_array($host, ['localhost', '127.0.0.1']) && !filter_var($host, FILTER_VALIDATE_IP) && !str_ends_with($host, '.test') && !str_ends_with($host, '.local')) {
                URL::forceScheme('https');
            }
        }
    }
}
