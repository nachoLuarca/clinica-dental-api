<?php

namespace App\Repositories;

use App\Models\Especialidad;
use App\Repositories\Contracts\EspecialidadRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EspecialidadRepository implements EspecialidadRepositoryInterface
{
    public function all(): Collection
    {
        return Especialidad::query()->with('treatments')->orderBy('nombre')->get();
    }

    public function find(int $id, array $with = []): ?Especialidad
    {
        return Especialidad::query()->with($with)->find($id);
    }

    public function create(array $data): Especialidad
    {
        return Especialidad::query()->create($data);
    }

    public function update(Especialidad $especialidad, array $data): Especialidad
    {
        $especialidad->update($data);

        return $especialidad->refresh();
    }

    public function delete(Especialidad $especialidad): void
    {
        $especialidad->delete();
    }

    public function publicasConTratamientosActivos(): Collection
    {
        return Especialidad::query()
            ->whereHas('treatments', fn ($q) => $q->where('activo', true))
            ->with(['treatments' => fn ($q) => $q->where('activo', true)])
            // Alias 'as profesionales_count': la relacion se llama
            // 'professionals' (en ingles, consistente con el resto del
            // modelo), pero el contrato publico expone la cuenta en
            // espanol, como el resto de las claves de este endpoint.
            ->withCount(['professionals as profesionales_count' => fn ($q) => $q->where('professionals.activo', true)])
            ->orderBy('nombre')
            ->get();
    }
}
