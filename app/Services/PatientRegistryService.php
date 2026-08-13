<?php

namespace App\Services;

use App\Models\Patient;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Registro clinico del paciente a cargo del STAFF (portal clinica).
 *
 * Distinto del PatientAuthService (paso 3), que gestiona el login del paciente.
 * Aqui el staff pre-registra la ficha; el paciente puede o no tener credenciales
 * de acceso (password nullable). Si el staff define una password, se hashea.
 */
class PatientRegistryService
{
    public function __construct(
        private readonly PatientRepositoryInterface $patients,
        private readonly TenantContext $tenant,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->patients->paginate($perPage);
    }

    public function find(int $id): Patient
    {
        return $this->patients->find($id, ['diagnoses'])
            ?? throw (new ModelNotFoundException)->setModel(Patient::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Patient
    {
        $this->assertEmailUnique($data['email']);

        return $this->patients->create($this->prepare($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Patient
    {
        $patient = $this->find($id);

        if (isset($data['email']) && $data['email'] !== $patient->email) {
            $this->assertEmailUnique($data['email']);
        }

        return $this->patients->update($patient, $this->prepare($data));
    }

    public function delete(int $id): void
    {
        $this->patients->delete($this->find($id));
    }

    /**
     * Hashea la password si viene; nunca se acepta un valor ya "hasheado" ni un
     * tenant_id del cliente.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(array $data): array
    {
        unset($data['tenant_id']);

        if (array_key_exists('password', $data)) {
            $data['password'] = $data['password'] !== null && $data['password'] !== ''
                ? Hash::make($data['password'])
                : null;
        }

        return $data;
    }

    private function assertEmailUnique(string $email): void
    {
        $tenantId = $this->tenant->tenantId();

        if ($tenantId !== null && $this->patients->findByTenantAndEmail($tenantId, $email) !== null) {
            throw ValidationException::withMessages([
                'email' => ['Ya existe un paciente con este correo en la clinica.'],
            ]);
        }
    }
}
