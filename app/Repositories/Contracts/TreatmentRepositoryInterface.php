<?php

namespace App\Repositories\Contracts;

use App\Models\Treatment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TreatmentRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Treatment>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?Treatment;

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Treatment>
     */
    public function findManyByIds(array $ids): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Treatment;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Treatment $treatment, array $data): Treatment;

    public function delete(Treatment $treatment): void;

    public function existsSlug(string $slug, ?int $excludeId = null): bool;

    /**
     * Reemplaza por completo el set de tratamientos que pertenecen a una
     * especialidad: los tratamientos fuera del set quedan sin especialidad
     * (especialidad_id = null), los del set pasan a apuntarle.
     *
     * @param  array<int, int>  $treatmentIds
     */
    public function syncEspecialidad(int $especialidadId, array $treatmentIds): void;

    /**
     * Duracion (minutos) del tratamiento activo mas largo de la especialidad.
     * Null si la especialidad no tiene ningun tratamiento activo.
     *
     * Usado por disponibilidad publica cuando el wizard todavia no fijo un
     * tratamiento puntual (entry points Especialidad/Profesional/Sucursal,
     * estilo Dentalink): se generan los slots con la duracion mas larga
     * posible para que CUALQUIER tratamiento de esa especialidad que el
     * paciente elija despues en Confirmar entre en el horario mostrado -evita
     * que un slot se vea libre y despues rebote con 409 por no alcanzar.
     */
    public function maxDuracionActivaParaEspecialidad(int $especialidadId): ?int;
}
