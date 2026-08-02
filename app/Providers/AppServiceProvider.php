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
        // Hosting providers commonly terminate TLS at an edge proxy. Keep
        // generated asset(), route(), and redirect URLs secure even when the
        // upstream PHP process receives the request over HTTP.
        $appUrl = (string) config('app.url');

        if ($this->app->environment('production') || str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }
    }
}
