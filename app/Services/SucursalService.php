<?php

namespace App\Services;

use App\Models\Sucursal;
use App\Repositories\Contracts\SucursalRepositoryInterface;
use App\Repositories\Contracts\SucursalScheduleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de negocio de sucursales (sedes de la clinica) y su horario de
 * atencion. Mismo patron que ProfessionalService con 'horarios'.
 */
class SucursalService
{
    public function __construct(
        private readonly SucursalRepositoryInterface $sucursales,
        private readonly SucursalScheduleRepositoryInterface $schedules,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->sucursales->paginate($perPage, ['horarios']);
    }

    public function find(int $id): Sucursal
    {
        return $this->sucursales->find($id, ['horarios'])
            ?? throw (new ModelNotFoundException)->setModel(Sucursal::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Sucursal
    {
        return DB::transaction(function () use ($data) {
            $horarios = $data['horarios'] ?? null;
            unset($data['horarios']);

            $sucursal = $this->sucursales->create($data);

            if (is_array($horarios)) {
                $this->schedules->syncForSucursal($sucursal, $horarios);
            }

            return $sucursal->load('horarios');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Sucursal
    {
        return DB::transaction(function () use ($id, $data) {
            $sucursal = $this->find($id);

            $horarios = $data['horarios'] ?? null;
            unset($data['horarios']);

            $this->sucursales->update($sucursal, $data);

            if (is_array($horarios)) {
                $this->schedules->syncForSucursal($sucursal, $horarios);
            }

            return $sucursal->refresh()->load('horarios');
        });
    }

    public function delete(int $id): void
    {
        $this->sucursales->delete($this->find($id));
    }

    /**
     * @return Collection<int, Sucursal>
     */
    public function catalogoPublico(): Collection
    {
        return $this->sucursales->activasConHorario();
    }
}
