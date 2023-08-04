<?php

namespace App\Providers;

use App\Src\Repositories\Company\CompanyWriteableRepository;
use Illuminate\Support\ServiceProvider;

class CompanyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind('CompanyRepositoryWriteable', function () {
            return new CompanyWriteableRepository();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
