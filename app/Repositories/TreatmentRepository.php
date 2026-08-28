<?php

namespace App\Repositories;

use App\Models\Treatment;
use App\Repositories\Contracts\TreatmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TreatmentRepository implements TreatmentRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Treatment::query()
            ->with('especialidad')
            ->latest('id')
            ->paginate($perPage);
    }

    public function find(int $id): ?Treatment
    {
        return Treatment::query()->with('especialidad')->find($id);
    }

    public function findManyByIds(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return Treatment::query()->whereIn('id', $ids)->get();
    }

    public function create(array $data): Treatment
    {
        return Treatment::query()->create($data);
    }

    public function update(Treatment $treatment, array $data): Treatment
    {
        $treatment->update($data);

        return $treatment->refresh();
    }

    public function delete(Treatment $treatment): void
    {
        $treatment->delete();
    }

    public function existsSlug(string $slug, ?int $excludeId = null): bool
    {
        return Treatment::query()
            ->where('slug', $slug)
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }

    public function syncEspecialidad(int $especialidadId, array $treatmentIds): void
    {
        Treatment::query()->where('especialidad_id', $especialidadId)->update(['especialidad_id' => null]);

        if ($treatmentIds !== []) {
            Treatment::query()->whereIn('id', $treatmentIds)->update(['especialidad_id' => $especialidadId]);
        }
    }

    public function maxDuracionActivaParaEspecialidad(int $especialidadId): ?int
    {
        $max = Treatment::query()
            ->where('especialidad_id', $especialidadId)
            ->where('activo', true)
            ->max('duracion_minutos');

        return $max !== null ? (int) $max : null;
    }
}
