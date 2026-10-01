<?php

namespace App\Providers;

use App\Src\Support\CurrentCompany;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
        $this->app->register(TelescopeServiceProvider::class);

        // Una instancia por request: dueño único de la empresa activa.
        $this->app->scoped(CurrentCompany::class, function ($app) {
            return new CurrentCompany($app['request']);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
