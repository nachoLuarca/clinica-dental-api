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
}
