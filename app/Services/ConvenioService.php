<?php

namespace App\Services;

use App\Models\Convenio;
use App\Repositories\Contracts\ConvenioRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Reglas de negocio de convenios. Manejo de logo: mismo patron que
 * TenantService (disco 'public', carpeta dedicada, borra el anterior al
 * reemplazar o al eliminar el convenio).
 */
class ConvenioService
{
    private const LOGO_DISK = 'public';

    private const LOGO_DIR = 'convenios';

    public function __construct(
        private readonly ConvenioRepositoryInterface $convenios,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->convenios->paginate($perPage);
    }

    public function find(int $id): Convenio
    {
        return $this->convenios->find($id)
            ?? throw (new ModelNotFoundException)->setModel(Convenio::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $logo = null): Convenio
    {
        if ($logo !== null) {
            $data['logo_path'] = $logo->store(self::LOGO_DIR, self::LOGO_DISK);
        }

        return $this->convenios->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data, ?UploadedFile $logo = null): Convenio
    {
        $convenio = $this->find($id);

        if ($logo !== null) {
            $this->eliminarLogoAnterior($convenio);
            $data['logo_path'] = $logo->store(self::LOGO_DIR, self::LOGO_DISK);
        }

        return $this->convenios->update($convenio, $data);
    }

    public function delete(int $id): void
    {
        $convenio = $this->find($id);

        $this->eliminarLogoAnterior($convenio);
        $this->convenios->delete($convenio);
    }

    /**
     * @return Collection<int, Convenio>
     */
    public function catalogoPublico(): Collection
    {
        return $this->convenios->activos();
    }

    private function eliminarLogoAnterior(Convenio $convenio): void
    {
        if ($convenio->logo_path !== null) {
            Storage::disk(self::LOGO_DISK)->delete($convenio->logo_path);
        }
    }
}
