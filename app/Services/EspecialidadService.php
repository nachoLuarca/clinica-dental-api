<?php

namespace App\Services;

use App\Models\Especialidad;
use App\Repositories\Contracts\EspecialidadRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de negocio del catalogo de especialidades y su mapeo a categorias
 * de tratamiento (paso 11: filtro de reserva por especialidad<->categoria).
 */
class EspecialidadService
{
    public function __construct(
        private readonly EspecialidadRepositoryInterface $especialidades,
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
        return $this->especialidades->find($id, ['categorias'])
            ?? throw (new ModelNotFoundException)->setModel(Especialidad::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Especialidad
    {
        return DB::transaction(function () use ($data) {
            $categorias = $data['categorias'] ?? null;
            unset($data['categorias']);

            $especialidad = $this->especialidades->create($data);

            if (is_array($categorias)) {
                $this->especialidades->syncCategorias($especialidad, $categorias);
            }

            return $especialidad->load('categorias');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Especialidad
    {
        return DB::transaction(function () use ($id, $data) {
            $especialidad = $this->find($id);

            $categorias = $data['categorias'] ?? null;
            unset($data['categorias']);

            $this->especialidades->update($especialidad, $data);

            if (is_array($categorias)) {
                $this->especialidades->syncCategorias($especialidad, $categorias);
            }

            return $especialidad->refresh()->load('categorias');
        });
    }

    public function delete(int $id): void
    {
        $this->especialidades->delete($this->find($id));
    }
}
