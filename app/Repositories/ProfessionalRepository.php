<?php

namespace App\Repositories;

use App\Models\Professional;
use App\Repositories\Contracts\ProfessionalRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ProfessionalRepository implements ProfessionalRepositoryInterface
{
    public function all(): Collection
    {
        return Professional::query()->get();
    }

    public function find(int $id): ?Professional
    {
        return Professional::query()->find($id);
    }

    public function create(array $data): Professional
    {
        // El tenant_id lo asigna el trait BelongsToTenant desde el TenantContext,
        // no se acepta desde $data.
        return Professional::query()->create($data);
    }

    public function update(Professional $professional, array $data): Professional
    {
        $professional->update($data);

        return $professional->refresh();
    }

    public function delete(Professional $professional): void
    {
        $professional->delete();
    }
}
