<?php

namespace App\Services;

use App\Models\Especialidad;
use App\Repositories\Contracts\EspecialidadRepositoryInterface;
use App\Repositories\Contracts\TreatmentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de negocio del catalogo de especialidades y su relacion con los
 * tratamientos que cubre (paso 11/12: filtro de reserva por especialidad,
 * via Treatment::especialidad_id).
 */
class EspecialidadService
{
    public function __construct(
        private readonly EspecialidadRepositoryInterface $especialidades,
        private readonly TreatmentRepositoryInterface $treatments,
    ) {}

    /**
     * @return Collection<int, Especialidad>
     */
    public function all(): Collection
    {
        return $this->especialidades->all();
    }

    public function find(int $id): Especialidad
    {
        return $this->especialidades->find($id, ['treatments'])
            ?? throw (new ModelNotFoundException)->setModel(Especialidad::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Especialidad
    {
        return DB::transaction(function () use ($data) {
            $treatmentIds = $data['treatment_ids'] ?? null;
            unset($data['treatment_ids']);

            $especialidad = $this->especialidades->create($data);

            if (is_array($treatmentIds)) {
                $this->treatments->syncEspecialidad($especialidad->id, $treatmentIds);
            }

            return $especialidad->load('treatments');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Especialidad
    {
        return DB::transaction(function () use ($id, $data) {
            $especialidad = $this->find($id);

            $treatmentIds = $data['treatment_ids'] ?? null;
            unset($data['treatment_ids']);

            $this->especialidades->update($especialidad, $data);

            if (is_array($treatmentIds)) {
                $this->treatments->syncEspecialidad($especialidad->id, $treatmentIds);
            }

            return $especialidad->refresh()->load('treatments');
        });
    }

    public function delete(int $id): void
    {
        $this->especialidades->delete($this->find($id));
    }

    /**
     * Catalogo publico (sin login): especialidad -> sus tratamientos activos
     * + cantidad de profesionales activos vinculados, ya armado por el
     * backend en una sola query (evita que el frontend reconstruya la
     * relacion pidiendo /publico/profesionales?treatment_id= por cada
     * especialidad).
     *
     * @return Collection<int, Especialidad>
     */
    public function catalogoPublico(): Collection
    {
        return $this->especialidades->publicasConTratamientosActivos();
    }
}
