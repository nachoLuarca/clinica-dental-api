<?php

namespace App\Services\Auth;

use App\Models\Tenant;
use App\Repositories\Contracts\StaffRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio de autenticacion del STAFF (guard 'staff').
 *
 * El tenant se resuelve SIEMPRE del slug de clinica validado contra la base,
 * nunca de un tenant_id crudo enviado por el cliente. El token emitido queda
 * ligado al modelo User (provider 'staff'), por lo que jamas sirve para el
 * guard 'paciente'.
 */
class StaffAuthService
{
    /** Nombre del token; tambien lo usamos como ability para trazabilidad. */
    private const TOKEN_NAME = 'staff';

    public function __construct(
        private readonly StaffRepositoryInterface $staff,
        private readonly TenantRepositoryInterface $tenants,
        private readonly RoleProvisioner $roles,
    ) {}

    /**
     * @param  array{clinica: string, name: string, email: string, password: string}  $data
     */
    public function register(array $data): AuthResult
    {
        $tenant = $this->resolveTenant($data['clinica']);

        if ($this->staff->findByTenantAndEmail($tenant->id, $data['email']) !== null) {
            throw ValidationException::withMessages([
                'email' => ['Ya existe una cuenta de staff con este correo en la clinica.'],
            ]);
        }

        $user = $this->staff->create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // Rol por defecto de menor privilegio: el auto-registro es publico
        // (solo requiere conocer el slug de la clinica), asi que nunca otorga
        // 'admin' automatico. Elevar a admin/profesional se hace a mano.
        $this->roles->asignarRol($user, 'recepcion');

        return new AuthResult($user, $user->createToken(self::TOKEN_NAME, ['staff'])->plainTextToken);
    }

    /**
     * @param  array{clinica: string, email: string, password: string}  $data
     */
    public function login(array $data): AuthResult
    {
        $tenant = $this->resolveTenant($data['clinica']);

        $user = $this->staff->findByTenantAndEmail($tenant->id, $data['email']);

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no coinciden con nuestros registros.'],
            ]);
        }

        return new AuthResult($user, $user->createToken(self::TOKEN_NAME, ['staff'])->plainTextToken);
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
