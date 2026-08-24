<?php

namespace App\Repositories\Contracts;

use App\Models\Sucursal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SucursalRepositoryInterface
{
    /**
     * @param  array<int, string>  $with
     * @return LengthAwarePaginator<int, Sucursal>
     */
    public function paginate(int $perPage = 15, array $with = []): LengthAwarePaginator;

    /**
     * @param  array<int, string>  $with
     */
    public function find(int $id, array $with = []): ?Sucursal;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Sucursal;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Sucursal $sucursal, array $data): Sucursal;

    public function delete(Sucursal $sucursal): void;

    /**
     * Catalogo publico: sucursales activas con su horario cargado.
     *
     * @return Collection<int, Sucursal>
     */
    public function activasConHorario(): Collection;
}
