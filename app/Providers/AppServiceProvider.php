<?php

namespace App\Providers;

use App\Src\Support\CurrentCompany;
use App\Src\Arba\Cot\CotNomenclador;
use App\Src\Arba\Cot\NomencladorArba;
use Illuminate\Support\ServiceProvider;
use App\Src\Arba\Cot\Transport\CotTransport;
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

        // El transporte hacia ARBA sale de configuración para poder
        // reemplazarlo en los tests: los tests no pueden salir a internet ni
        // depender de que el servicio de ARBA esté arriba.
        $this->app->bind(CotTransport::class, function ($app) {
            return $app->make(config('cot.transport'));
        });

        // El nomenclador de productos de ARBA se consulta en la base.
        $this->app->bind(CotNomenclador::class, NomencladorArba::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
