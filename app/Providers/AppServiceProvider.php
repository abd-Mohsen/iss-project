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
        //TODO
        //TODO hybrid encrypt certifivates and key, not just file
        //TODO see if you have to do something extra to save the files securely
    }
}
