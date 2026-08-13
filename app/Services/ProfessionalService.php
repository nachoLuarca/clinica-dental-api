<?php

namespace App\Services;

use App\Models\Professional;
use App\Repositories\Contracts\ProfessionalRepositoryInterface;
use App\Repositories\Contracts\ProfessionalScheduleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de negocio de profesionales. El controller solo orquesta; toda la
 * logica (incl. horarios) vive aqui y delega el acceso a datos en repositorios.
 */
class ProfessionalService
{
    public function __construct(
        private readonly ProfessionalRepositoryInterface $professionals,
        private readonly ProfessionalScheduleRepositoryInterface $schedules,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->professionals->paginate($perPage, ['schedules']);
    }

    public function find(int $id): Professional
    {
        return $this->professionals->find($id, ['schedules'])
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

            $professional = $this->professionals->create($data);

            if (is_array($horarios)) {
                $this->schedules->syncForProfessional($professional, $horarios);
            }

            return $professional->load('schedules');
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

            $this->professionals->update($professional, $data);

            // Solo se reemplazan los horarios si vienen en la peticion.
            if (is_array($horarios)) {
                $this->schedules->syncForProfessional($professional, $horarios);
            }

            return $professional->refresh()->load('schedules');
        });
    }

    public function delete(int $id): void
    {
        $this->professionals->delete($this->find($id));
    }
}
