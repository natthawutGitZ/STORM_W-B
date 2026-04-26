<?php

namespace App\Providers;

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
        // Force HTTPS when behind Nginx reverse proxy
        if (config('app.env') === 'production' || !empty(env('TRUSTED_PROXIES'))) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
