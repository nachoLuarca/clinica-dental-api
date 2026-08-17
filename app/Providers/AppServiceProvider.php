<?php

namespace App\Providers;

use App\Repositories\AppointmentRepository;
use App\Repositories\BudgetRepository;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\BudgetRepositoryInterface;
use App\Repositories\Contracts\DiagnosisRepositoryInterface;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\ProfessionalRepositoryInterface;
use App\Repositories\Contracts\ProfessionalScheduleRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\StaffRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\TreatmentRepositoryInterface;
use App\Notificaciones\Canales\CorreoNotificacionServicio;
use App\Notificaciones\Canales\WhatsAppNotificacionServicio;
use App\Repositories\DiagnosisRepository;
use App\Repositories\PatientRepository;
use App\Repositories\ProfessionalRepository;
use App\Repositories\ProfessionalScheduleRepository;
use App\Repositories\RoleRepository;
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
        $this->app->bind(
            AppointmentRepositoryInterface::class,
            AppointmentRepository::class,
        );
        $this->app->bind(
            RoleRepositoryInterface::class,
            RoleRepository::class,
        );

        // Canales de notificacion (paso 6). Cada uno implementa la misma interfaz
        // NotificacionServicio; se les inyecta su config desde .env (nunca
        // secretos hardcodeados). El resto del codigo no depende de estas clases
        // concretas, sino del NotificadorDeCitas / CanalNotificacionManager.
        $this->app->bind(CorreoNotificacionServicio::class, function () {
            return new CorreoNotificacionServicio(config('notificaciones.correo.mailer'));
        });
        $this->app->bind(WhatsAppNotificacionServicio::class, function () {
            return new WhatsAppNotificacionServicio(config('notificaciones.whatsapp'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rate limiting del login. Se limita por combinacion de clinica + email
        // + IP, para que intentos contra una cuenta no bloqueen a otras y para
        // frenar fuerza bruta. El limite se puede ajustar por .env.
        RateLimiter::for('login', function (Request $request) {
            $key = implode('|', [
                (string) $request->input('clinica'),
                (string) $request->input('email'),
                $request->ip(),
            ]);

            return Limit::perMinute((int) config('seguridad.rate_limits.login', 5))->by($key);
        });

        // Rate limiting del registro (staff y paciente). Frena la creacion masiva
        // de cuentas. Se limita por clinica + IP.
        RateLimiter::for('register', function (Request $request) {
            $key = implode('|', [
                (string) $request->input('clinica'),
                $request->ip(),
            ]);

            return Limit::perMinute((int) config('seguridad.rate_limits.register', 10))->by($key);
        });

        // Rate limiting de disponibilidad para usuarios AUTENTICADOS (staff y
        // paciente logueado). Se limita por usuario si lo hay, si no por IP.
        RateLimiter::for('availability', function (Request $request) {
            return Limit::perMinute((int) config('seguridad.rate_limits.availability', 60))
                ->by($request->user()?->id ? 'u'.$request->user()->id : $request->ip());
        });

        // Rate limiting de los endpoints PUBLICOS (catalogo y disponibilidad del
        // sitio de pacientes). Como pide la arquitectura, se limita POR TENANT Y
        // POR IP. Se toma el slug de clinica directamente del header (mismo que
        // valida 'tenant.publico') en vez del TenantContext, para no depender del
        // orden en que corran throttle vs. el middleware de tenant: asi la clave
        // es determinista y cada clinica/IP tiene su propio cupo.
        RateLimiter::for('publico', function (Request $request) {
            $slug = trim((string) $request->header(config('tenancy.public_header', 'X-Clinica'), ''));
            $key = 'c:'.($slug !== '' ? $slug : 'sin-clinica').'|ip:'.$request->ip();

            return Limit::perMinute((int) config('seguridad.rate_limits.publico', 30))->by($key);
        });
    }
}
