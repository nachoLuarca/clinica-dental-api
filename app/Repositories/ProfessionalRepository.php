<?php

namespace App\Repositories;

use App\Models\Professional;
use App\Repositories\Contracts\ProfessionalRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProfessionalRepository implements ProfessionalRepositoryInterface
{
    public function all(): Collection
    {
        return Professional::query()->get();
    }

    public function allActivos(): Collection
    {
        return Professional::query()->where('activo', true)->with('schedules')->orderBy('nombre')->get();
    }

    public function allActivosParaEspecialidad(?int $especialidadId): Collection
    {
        $query = Professional::query()->where('activo', true)->with(['schedules', 'especialidades']);

        // Sin especialidad (tratamiento no la tiene asignada): no se filtra
        // (ver contrato).
        if ($especialidadId !== null) {
            $query->whereHas(
                'especialidades',
                fn ($q) => $q->where('especialidades.id', $especialidadId),
            );
        }

        return $query->orderBy('nombre')->get();
    }

    public function paginate(int $perPage = 15, array $with = []): LengthAwarePaginator
    {
        return Professional::query()
            ->with($with)
            ->latest('id')
            ->paginate($perPage);
    }

    public function find(int $id, array $with = []): ?Professional
    {
        return Professional::query()->with($with)->find($id);
    }

    public function create(array $data): Professional
    {
        // El tenant_id lo asigna el trait BelongsToTenant desde el TenantContext,
        // no se acepta desde $data.
        return Professional::query()->create($data);
    }

    public function update(Professional $professional, array $data): Professional
    {
        $professional->update($data);

        return $professional->refresh();
    }

    public function delete(Professional $professional): void
    {
        $professional->delete();
    }
}
