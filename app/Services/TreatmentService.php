<?php

namespace App\Services;

use App\Models\Treatment;
use App\Repositories\Contracts\TreatmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

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
        $data['slug'] = $this->slugUnico($data['nombre']);

        return $this->treatments->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Treatment
    {
        $treatment = $this->find($id);

        if (isset($data['nombre']) && $data['nombre'] !== $treatment->nombre) {
            $data['slug'] = $this->slugUnico($data['nombre'], excluir: $treatment->id);
        }

        return $this->treatments->update($treatment, $data);
    }

    public function delete(int $id): void
    {
        $this->treatments->delete($this->find($id));
    }

    /**
     * Deriva el slug del nombre (nunca lo manda el cliente): unico por tenant
     * (la query de existsSlug ya queda scopeada por el TenantScope del
     * modelo). Si hay colision, agrega un sufijo numerico.
     */
    private function slugUnico(string $nombre, ?int $excluir = null): string
    {
        $base = Str::slug($nombre);
        $slug = $base;
        $sufijo = 2;

        while ($this->treatments->existsSlug($slug, $excluir)) {
            $slug = "{$base}-{$sufijo}";
            $sufijo++;
        }

        return $slug;
    }
}
