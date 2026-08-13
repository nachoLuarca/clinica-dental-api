<?php

namespace App\Services;

use App\Models\Treatment;
use App\Repositories\Contracts\TreatmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Reglas de negocio del catalogo de tratamientos/servicios.
 */
class TreatmentService
{
    public function __construct(
        private readonly TreatmentRepositoryInterface $treatments,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->treatments->paginate($perPage);
    }

    public function find(int $id): Treatment
    {
        return $this->treatments->find($id)
            ?? throw (new ModelNotFoundException)->setModel(Treatment::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Treatment
    {
        return $this->treatments->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Treatment
    {
        return $this->treatments->update($this->find($id), $data);
    }

    public function delete(int $id): void
    {
        $this->treatments->delete($this->find($id));
    }
}
