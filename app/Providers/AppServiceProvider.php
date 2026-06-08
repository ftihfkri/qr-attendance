<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        // In production, generate HTTPS URLs (behind Railway's TLS proxy).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
