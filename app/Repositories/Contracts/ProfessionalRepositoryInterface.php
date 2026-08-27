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
     * Profesionales activos, con horarios cargados. Usado por el listado
     * publico y por el modo "cualquier profesional disponible" de
     * disponibilidad/reservas.
     *
     * @return Collection<int, Professional>
     */
    public function allActivos(): Collection;

    /**
     * Profesionales activos elegibles para la especialidad de un tratamiento
     * dado (paso 11: filtro de reserva por especialidad, via FK real
     * Treatment::especialidad_id), opcionalmente acotados a una sede.
     *
     * Si $especialidadId es null (tratamiento sin especialidad asignada), no
     * filtra por especialidad (se comporta igual que allActivos()) para no
     * romper clinicas que aun no adoptaron el catalogo de especialidades. Lo
     * mismo con $sucursalId null: no filtra por sede.
     *
     * @return Collection<int, Professional>
     */
    public function allActivosParaEspecialidad(?int $especialidadId, ?int $sucursalId = null): Collection;

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
