<?php

namespace App\Services\Publico;

use App\Models\Patient;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Resuelve al paciente para el sitio publico (sin login) a partir de RUT +
 * fecha de nacimiento, dentro del tenant ya fijado por el middleware
 * 'tenant.publico'. Nunca revela si el RUT existe o no en la clinica: un
 * RUT correcto con la fecha equivocada da el mismo error generico que un
 * RUT que no existe, para no habilitar enumeracion de pacientes.
 */
class PatientLookupService
{
    public function __construct(
        private readonly PatientRepositoryInterface $patients,
        private readonly TenantContext $tenant,
    ) {}

    public function resolver(string $rut, string $fechaNacimiento): Patient
    {
        $tenantId = $this->tenant->tenantId()
            ?? throw new \RuntimeException('No hay tenant activo en el contexto.');

        $patient = $this->patients->findByTenantRutAndBirthdate(
            $tenantId,
            Patient::normalizarRut($rut),
            $fechaNacimiento,
        );

        if ($patient === null) {
            throw ValidationException::withMessages([
                'rut' => ['No encontramos un paciente con ese RUT y fecha de nacimiento en esta clinica.'],
            ]);
        }

        return $patient;
    }
}
