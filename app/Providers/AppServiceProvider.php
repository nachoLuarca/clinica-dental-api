<?php

namespace App\Providers;

use App\Repositories\BudgetRepository;
use App\Repositories\Contracts\BudgetRepositoryInterface;
use App\Repositories\Contracts\DiagnosisRepositoryInterface;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\ProfessionalRepositoryInterface;
use App\Repositories\Contracts\ProfessionalScheduleRepositoryInterface;
use App\Repositories\Contracts\StaffRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\TreatmentRepositoryInterface;
use App\Repositories\DiagnosisRepository;
use App\Repositories\PatientRepository;
use App\Repositories\ProfessionalRepository;
use App\Repositories\ProfessionalScheduleRepository;
use App\Repositories\StaffRepository;
use App\Repositories\TenantRepository;
use App\Repositories\TreatmentRepository;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->bind(
            TenantRepositoryInterface::class,
            TenantRepository::class,
        );
        $this->app->bind(
            StaffRepositoryInterface::class,
            StaffRepository::class,
        );
        $this->app->bind(
            PatientRepositoryInterface::class,
            PatientRepository::class,
        );
        $this->app->bind(
            ProfessionalScheduleRepositoryInterface::class,
            ProfessionalScheduleRepository::class,
        );
        $this->app->bind(
            DiagnosisRepositoryInterface::class,
            DiagnosisRepository::class,
        );
        $this->app->bind(
            TreatmentRepositoryInterface::class,
            TreatmentRepository::class,
        );
        $this->app->bind(
            BudgetRepositoryInterface::class,
            BudgetRepository::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rate limiting del login (paso 3; el detalle fino es el paso 7). Se
        // limita por combinacion de clinica + email + IP, para que intentos
        // contra una cuenta no bloqueen a otras y para frenar fuerza bruta.
        RateLimiter::for('login', function (Request $request) {
            $key = implode('|', [
                (string) $request->input('clinica'),
                (string) $request->input('email'),
                $request->ip(),
            ]);

            return Limit::perMinute(5)->by($key);
        });
    }
}
