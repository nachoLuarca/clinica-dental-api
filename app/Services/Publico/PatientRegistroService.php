<?php

namespace App\Services\Publico;

use App\Models\Patient;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Support\TurnstileVerifier;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Paso de Identificacion por RUT del flujo de reserva publico: confirma si
 * un RUT ya es paciente de la clinica, da de alta uno nuevo sin login (sin
 * password, sin token de sesion) cuando no lo es, y resuelve el paciente ya
 * identificado para el resto del wizard (ej. crear la reserva en el paso
 * Confirmar, ver Publico\PatientAppointmentController::store).
 */
class PatientRegistroService
{
    public function __construct(
        private readonly PatientRepositoryInterface $patients,
        private readonly TurnstileVerifier $turnstile,
        private readonly TenantContext $tenant,
    ) {}

    public function existeRut(string $rut, ?string $turnstileToken, ?string $ip): bool
    {
        return $this->buscarPorRut($rut, $turnstileToken, $ip) !== null;
    }

    /**
     * Resuelve al paciente ya identificado (paso 1) por RUT, para el resto
     * del wizard SIN sesion. Turnstile se vuelve a exigir aca (no se reusa
     * el token del paso 1: son de un solo uso y expiran en minutos, no
     * duran todo el wizard) para que este endpoint -que crea una cita
     * real, no solo consulta- no quede abierto a cualquiera que conozca un
     * RUT ajeno.
     */
    public function resolverIdentificado(string $rut, ?string $turnstileToken, ?string $ip): Patient
    {
        return $this->buscarPorRut($rut, $turnstileToken, $ip)
            ?? throw (new ModelNotFoundException)->setModel(Patient::class);
    }

    private function buscarPorRut(string $rut, ?string $turnstileToken, ?string $ip): ?Patient
    {
        if (! $this->turnstile->verificar($turnstileToken, $ip)) {
            throw ValidationException::withMessages([
                'turnstile_token' => ['No pudimos verificar que sos una persona. Intenta de nuevo.'],
            ]);
        }

        $tenantId = $this->tenant->tenantId()
            ?? throw new \RuntimeException('No hay tenant activo en el contexto.');

        return $this->patients->findByTenantAndRut($tenantId, Patient::normalizarRut($rut));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function registrar(array $data): Patient
    {
        unset($data['acepta_tratamiento_datos']);
        $data['datos_aceptados_at'] = Carbon::now();
        $data['password'] = null;

        return $this->patients->create($data);
    }
}
