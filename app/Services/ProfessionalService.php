<?php

namespace App\Services;

use App\Models\Professional;
use App\Repositories\Contracts\ProfessionalEspecialidadRepositoryInterface;
use App\Repositories\Contracts\ProfessionalRepositoryInterface;
use App\Repositories\Contracts\ProfessionalScheduleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de negocio de profesionales. El controller solo orquesta; toda la
 * logica (incl. horarios y especialidades) vive aqui y delega el acceso a
 * datos en repositorios.
 */
class ProfessionalService
{
    public function __construct(
        private readonly ProfessionalRepositoryInterface $professionals,
        private readonly ProfessionalScheduleRepositoryInterface $schedules,
        private readonly ProfessionalEspecialidadRepositoryInterface $especialidades,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->professionals->paginate($perPage, ['schedules', 'especialidades']);
    }

    public function find(int $id): Professional
    {
        return $this->professionals->find($id, ['schedules', 'especialidades'])
            ?? throw (new ModelNotFoundException)->setModel(Professional::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Professional
    {
        return DB::transaction(function () use ($data) {
            $horarios = $data['horarios'] ?? null;
            unset($data['horarios']);

            $especialidadIds = $data['especialidades'] ?? null;
            unset($data['especialidades']);

            $professional = $this->professionals->create($data);

            if (is_array($horarios)) {
                $this->schedules->syncForProfessional($professional, $horarios);
            }

            if (is_array($especialidadIds)) {
                $this->especialidades->syncForProfessional($professional, $especialidadIds);
            }

            return $professional->load(['schedules', 'especialidades']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Professional
    {
        return DB::transaction(function () use ($id, $data) {
            $professional = $this->find($id);

            $horarios = $data['horarios'] ?? null;
            unset($data['horarios']);

            $especialidadIds = $data['especialidades'] ?? null;
            unset($data['especialidades']);

            $this->professionals->update($professional, $data);

            // Solo se reemplazan los horarios/especialidades si vienen en la peticion.
            if (is_array($horarios)) {
                $this->schedules->syncForProfessional($professional, $horarios);
            }

            if (is_array($especialidadIds)) {
                $this->especialidades->syncForProfessional($professional, $especialidadIds);
            }

            return $professional->refresh()->load(['schedules', 'especialidades']);
        });
    }

    public function delete(int $id): void
    {
        $this->professionals->delete($this->find($id));
    }
}
