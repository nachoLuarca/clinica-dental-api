<?php

namespace App\Repositories\Contracts;

use App\Models\Professional;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contrato del repositorio de profesionales.
 *
 * El repositorio es la UNICA capa que habla con el Model Eloquent. Todas las
 * consultas quedan automaticamente aisladas por tenant gracias al TenantScope
 * del modelo, sin que esta capa deba filtrar manualmente por tenant_id.
 */
interface ProfessionalRepositoryInterface
{
    /**
     * @return Collection<int, Professional>
     */
    public function all(): Collection;

    /**
     * Listado paginado. $with permite eager loading explicito (evitar N+1).
     *
     * @param  array<int, string>  $with
     * @return LengthAwarePaginator<int, Professional>
     */
    public function paginate(int $perPage = 15, array $with = []): LengthAwarePaginator;

    /**
     * @param  array<int, string>  $with
     */
    public function find(int $id, array $with = []): ?Professional;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Professional;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Professional $professional, array $data): Professional;

    public function delete(Professional $professional): void;
}
