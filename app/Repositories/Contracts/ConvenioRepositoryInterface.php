<?php

namespace App\Repositories\Contracts;

use App\Models\Convenio;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ConvenioRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?Convenio;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Convenio;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Convenio $convenio, array $data): Convenio;

    public function delete(Convenio $convenio): void;

    /**
     * Catalogo publico: convenios activos.
     *
     * @return Collection<int, Convenio>
     */
    public function activos(): Collection;
}
