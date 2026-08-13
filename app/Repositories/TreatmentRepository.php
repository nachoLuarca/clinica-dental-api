<?php

namespace App\Repositories;

use App\Models\Treatment;
use App\Repositories\Contracts\TreatmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TreatmentRepository implements TreatmentRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Treatment::query()
            ->latest('id')
            ->paginate($perPage);
    }

    public function find(int $id): ?Treatment
    {
        return Treatment::query()->find($id);
    }

    public function findManyByIds(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return Treatment::query()->whereIn('id', $ids)->get();
    }

    public function create(array $data): Treatment
    {
        return Treatment::query()->create($data);
    }

    public function update(Treatment $treatment, array $data): Treatment
    {
        $treatment->update($data);

        return $treatment->refresh();
    }

    public function delete(Treatment $treatment): void
    {
        $treatment->delete();
    }
}
