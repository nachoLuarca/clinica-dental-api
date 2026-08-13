<?php

namespace App\Repositories;

use App\Models\Patient;
use App\Models\Scopes\TenantScope;
use App\Repositories\Contracts\PatientRepositoryInterface;

class PatientRepository implements PatientRepositoryInterface
{
    /**
     * Busca paciente por (tenant, email). Igual que en StaffRepository, ignora
     * el TenantScope global porque durante login/registro no hay tenant activo;
     * el filtro por tenant lo impone el tenant ya resuelto de la clinica.
     */
    public function findByTenantAndEmail(int $tenantId, string $email): ?Patient
    {
        return Patient::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('email', $email)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Patient
    {
        return Patient::query()->create($data);
    }
}
