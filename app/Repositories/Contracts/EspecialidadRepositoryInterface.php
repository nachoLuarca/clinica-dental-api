<?php

namespace App\Repositories\Contracts;

use App\Models\Especialidad;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contrato del repositorio de especialidades (catalogo por tenant).
 */
interface EspecialidadRepositoryInterface
{
    /**
     * @return Collection<int, Especialidad>
     */
    public function all(): Collection;

    public function find(int $id, array $with = []): ?Especialidad;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Especialidad;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Especialidad $especialidad, array $data): Especialidad;

    public function delete(Especialidad $especialidad): void;

    /**
     * Catalogo publico: especialidades con al menos un tratamiento activo,
     * cada una con sus tratamientos activos cargados y la cantidad de
     * profesionales activos vinculados (mismo criterio que
     * ProfessionalRepository::allActivosParaEspecialidad, solo que aca se
     * expone contado en vez de filtrando un listado).
     *
     * @return Collection<int, Especialidad>
     */
    public function publicasConTratamientosActivos(): Collection;
}
