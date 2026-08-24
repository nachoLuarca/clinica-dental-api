<?php

namespace App\Repositories;

use App\Models\Convenio;
use App\Repositories\Contracts\ConvenioRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ConvenioRepository implements ConvenioRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Convenio::query()->latest('id')->paginate($perPage);
    }

    public function find(int $id): ?Convenio
    {
        return Convenio::query()->find($id);
    }

    public function create(array $data): Convenio
    {
        return Convenio::query()->create($data);
    }

    public function update(Convenio $convenio, array $data): Convenio
    {
        $convenio->update($data);

        return $convenio->refresh();
    }

    public function delete(Convenio $convenio): void
    {
        $convenio->delete();
    }

    public function activos(): Collection
    {
        return Convenio::query()->where('activo', true)->orderBy('nombre')->get();
    }
}
