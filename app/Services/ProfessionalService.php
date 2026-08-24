<?php

namespace App\Services;

use App\Models\Professional;
use App\Repositories\Contracts\ProfessionalEspecialidadRepositoryInterface;
use App\Repositories\Contracts\ProfessionalRepositoryInterface;
use App\Repositories\Contracts\ProfessionalScheduleRepositoryInterface;
use App\Support\AvailabilityCache;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reglas de negocio de profesionales. El controller solo orquesta; toda la
 * logica (incl. horarios, especialidades y foto) vive aqui y delega el
 * acceso a datos en repositorios.
 */
class ProfessionalService
{
    private const FOTO_DISK = 'public';

    private const FOTO_DIR = 'profesionales';

    private const WITH = ['schedules', 'especialidades', 'sucursal'];

    public function __construct(
        private readonly ProfessionalRepositoryInterface $professionals,
        private readonly ProfessionalScheduleRepositoryInterface $schedules,
        private readonly ProfessionalEspecialidadRepositoryInterface $especialidades,
        private readonly AvailabilityCache $cache,
        private readonly TenantContext $tenant,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->professionals->paginate($perPage, self::WITH);
    }

    public function find(int $id): Professional
    {
        return $this->professionals->find($id, self::WITH)
            ?? throw (new ModelNotFoundException)->setModel(Professional::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $foto = null): Professional
    {
        return DB::transaction(function () use ($data, $foto) {
            $horarios = $data['horarios'] ?? null;
            unset($data['horarios']);

            $especialidadIds = $data['especialidades'] ?? null;
            unset($data['especialidades']);

            if ($foto !== null) {
                $data['foto_path'] = $foto->store(self::FOTO_DIR, self::FOTO_DISK);
            }

            $professional = $this->professionals->create($data);

            if (is_array($horarios)) {
                $this->schedules->syncForProfessional($professional, $horarios);
            }

            if (is_array($especialidadIds)) {
                $this->especialidades->syncForProfessional($professional, $especialidadIds);
            }

            return $professional->load(self::WITH);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data, ?UploadedFile $foto = null): Professional
    {
        return DB::transaction(function () use ($id, $data, $foto) {
            $professional = $this->find($id);

            $horarios = $data['horarios'] ?? null;
            unset($data['horarios']);

            $especialidadIds = $data['especialidades'] ?? null;
            unset($data['especialidades']);

            if ($foto !== null) {
                $this->eliminarFotoAnterior($professional);
                $data['foto_path'] = $foto->store(self::FOTO_DIR, self::FOTO_DISK);
            }

            $this->professionals->update($professional, $data);

            // Solo se reemplazan los horarios/especialidades si vienen en la peticion.
            if (is_array($horarios)) {
                $this->schedules->syncForProfessional($professional, $horarios);

                // Un horario editado cambia la grilla de CUALQUIER fecha
                // futura, no solo una: sin esto, una fecha ya cacheada antes
                // del cambio seguiria mostrando los slots viejos.
                $this->cache->forgetForProfessional((int) $this->tenant->tenantId(), $professional->id);
            }

            if (is_array($especialidadIds)) {
                $this->especialidades->syncForProfessional($professional, $especialidadIds);
            }

            return $professional->refresh()->load(self::WITH);
        });
    }

    public function delete(int $id): void
    {
        $professional = $this->find($id);

        $this->eliminarFotoAnterior($professional);
        $this->professionals->delete($professional);

        $this->cache->forgetForProfessional((int) $this->tenant->tenantId(), $professional->id);
    }

    private function eliminarFotoAnterior(Professional $professional): void
    {
        if ($professional->foto_path !== null) {
            Storage::disk(self::FOTO_DISK)->delete($professional->foto_path);
        }
    }
}
