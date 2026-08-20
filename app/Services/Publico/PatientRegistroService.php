<?php

namespace App\Services\Publico;

use App\Models\Patient;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Support\TurnstileVerifier;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Paso de Identificacion por RUT del flujo de reserva publico: confirma si
 * un RUT ya es paciente de la clinica, y da de alta uno nuevo sin login
 * (sin password, sin token de sesion) cuando no lo es.
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
        if (! $this->turnstile->verificar($turnstileToken, $ip)) {
            throw ValidationException::withMessages([
                'turnstile_token' => ['No pudimos verificar que sos una persona. Intenta de nuevo.'],
            ]);
        }

        $tenantId = $this->tenant->tenantId()
            ?? throw new \RuntimeException('No hay tenant activo en el contexto.');

        return $this->patients->findByTenantAndRut($tenantId, Patient::normalizarRut($rut)) !== null;
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
