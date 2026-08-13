<?php

namespace App\Providers;

use App\Repositories\Contracts\ProfessionalRepositoryInterface;
use App\Repositories\ProfessionalRepository;
use App\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Tenant activo del request. Singleton para que middleware, scope y
        // modelos compartan la misma instancia durante todo el ciclo.
        $this->app->singleton(TenantContext::class);

        // Bindings de repositorios (Repository Pattern + DI).
        $this->app->bind(
            ProfessionalRepositoryInterface::class,
            ProfessionalRepository::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
