<?php

namespace App\Services\Auth;

use App\Models\Tenant;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio de autenticacion del PACIENTE (guard 'paciente').
 *
 * Mismo patron que StaffAuthService: el tenant se resuelve del slug de clinica
 * validado en base. El token emitido queda ligado al modelo Patient (provider
 * 'pacientes'); nunca autentica el guard 'staff'.
 */
class PatientAuthService
{
    private const TOKEN_NAME = 'paciente';

    public function __construct(
        private readonly PatientRepositoryInterface $patients,
        private readonly TenantRepositoryInterface $tenants,
    ) {}

    /**
     * @param  array{clinica: string, nombre: string, email: string, password: string, fecha_nacimiento: string}  $data
     */
    public function register(array $data): AuthResult
    {
        $tenant = $this->resolveTenant($data['clinica']);

        if ($this->patients->findByTenantAndEmail($tenant->id, $data['email']) !== null) {
            throw ValidationException::withMessages([
                'email' => ['Ya existe un paciente con este correo en la clinica.'],
            ]);
        }

        $patient = $this->patients->create([
            'tenant_id' => $tenant->id,
            'nombre' => $data['nombre'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'fecha_nacimiento' => $data['fecha_nacimiento'],
        ]);

        return new AuthResult($patient, $patient->createToken(self::TOKEN_NAME, ['paciente'])->plainTextToken);
    }

    /**
     * @param  array{clinica: string, email: string, password: string}  $data
     */
    public function login(array $data): AuthResult
    {
        $tenant = $this->resolveTenant($data['clinica']);

        $patient = $this->patients->findByTenantAndEmail($tenant->id, $data['email']);

        if ($patient === null || ! Hash::check($data['password'], $patient->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no coinciden con nuestros registros.'],
            ]);
        }

        return new AuthResult($patient, $patient->createToken(self::TOKEN_NAME, ['paciente'])->plainTextToken);
    }

    private function resolveTenant(string $slug): Tenant
    {
        $tenant = $this->tenants->findBySlug($slug);

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'clinica' => ['La clinica indicada no existe.'],
            ]);
        }

        return $tenant;
    }
}
