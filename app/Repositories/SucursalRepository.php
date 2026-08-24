<?php

namespace App\Repositories;

use App\Models\Sucursal;
use App\Repositories\Contracts\SucursalRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SucursalRepository implements SucursalRepositoryInterface
{
    public function paginate(int $perPage = 15, array $with = []): LengthAwarePaginator
    {
        return Sucursal::query()
            ->with($with)
            ->latest('id')
            ->paginate($perPage);
    }

    public function find(int $id, array $with = []): ?Sucursal
    {
        return Sucursal::query()->with($with)->find($id);
    }

    public function create(array $data): Sucursal
    {
        return Sucursal::query()->create($data);
    }

    public function update(Sucursal $sucursal, array $data): Sucursal
    {
        $sucursal->update($data);

        return $sucursal->refresh();
    }

    public function delete(Sucursal $sucursal): void
    {
        $sucursal->delete();
    }

    public function activasConHorario(): Collection
    {
        return Sucursal::query()
            ->where('activo', true)
            ->with('horarios')
            ->orderBy('nombre')
            ->get();
    }
}
