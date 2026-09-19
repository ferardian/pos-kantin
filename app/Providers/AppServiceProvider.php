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
        if (!app()->runningInConsole()) {
            $proto = request()->header('x-forwarded-proto');
            $host = request()->getHost();
            if ($proto === 'https' || (!filter_var($host, FILTER_VALIDATE_IP) && !in_array($host, ['localhost', '127.0.0.1']) && !str_ends_with($host, '.test') && !str_ends_with($host, '.local'))) {
                URL::forceScheme('https');
            }
        }
    }
}
