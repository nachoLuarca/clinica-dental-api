<?php

namespace App\Repositories;

use App\Models\Patient;
use App\Models\Scopes\TenantScope;
use App\Repositories\Contracts\PatientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

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

    public function findByTenantAndRut(int $tenantId, string $rut): ?Patient
    {
        return Patient::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('rut', $rut)
            ->first();
    }

    public function findByTenantRutAndBirthdate(int $tenantId, string $rut, string $fechaNacimiento): ?Patient
    {
        return Patient::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('rut', $rut)
            ->whereDate('fecha_nacimiento', $fechaNacimiento)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Patient
    {
        return Patient::query()->create($data);
    }

    public function paginate(int $perPage = 15, array $with = [], ?string $search = null): LengthAwarePaginator
    {
        return Patient::query()
            ->with($with)
            ->when($search !== null && $search !== '', fn ($query) => $this->aplicarBusqueda($query, $search))
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * LOWER(...) LIKE en vez de 'ilike' (especifico de Postgres) para que la
     * busqueda funcione igual en SQLite (tests). El rut se compara tambien
     * normalizado (sin puntos): asi buscar "12.345.678-9" encuentra al
     * paciente aunque en la BD el rut se guarde sin puntos.
     */
    private function aplicarBusqueda(Builder $query, string $search): void
    {
        $termino = '%'.mb_strtolower($search).'%';
        $terminoRut = '%'.mb_strtolower(Patient::normalizarRut($search)).'%';

        $query->where(function (Builder $q) use ($termino, $terminoRut) {
            $q->whereRaw('LOWER(nombre) LIKE ?', [$termino])
                ->orWhereRaw('LOWER(apellido) LIKE ?', [$termino])
                ->orWhereRaw('LOWER(email) LIKE ?', [$termino])
                ->orWhereRaw('LOWER(rut) LIKE ?', [$terminoRut]);
        });
    }

    public function find(int $id, array $with = []): ?Patient
    {
        return Patient::query()->with($with)->find($id);
    }

    public function update(Patient $patient, array $data): Patient
    {
        $patient->update($data);

        return $patient->refresh();
    }

    public function delete(Patient $patient): void
    {
        $patient->delete();
    }
}
