<?php

namespace App\Repositories\Contracts;

use App\Models\Treatment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TreatmentRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Treatment>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?Treatment;

    /**
     * @param  array<int, int>  $ids
     * @return \Illuminate\Database\Eloquent\Collection<int, Treatment>
     */
    public function findManyByIds(array $ids): \Illuminate\Database\Eloquent\Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Treatment;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Treatment $treatment, array $data): Treatment;

    public function delete(Treatment $treatment): void;
}
