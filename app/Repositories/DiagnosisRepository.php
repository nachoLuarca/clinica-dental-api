<?php

namespace App\Repositories;

use App\Models\Diagnosis;
use App\Repositories\Contracts\DiagnosisRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DiagnosisRepository implements DiagnosisRepositoryInterface
{
    public function paginateForPatient(int $patientId, int $perPage = 15, array $with = []): LengthAwarePaginator
    {
        return Diagnosis::query()
            ->where('patient_id', $patientId)
            ->with($with)
            ->latest('fecha')
            ->latest('id')
            ->paginate($perPage);
    }

    public function find(int $id, array $with = []): ?Diagnosis
    {
        return Diagnosis::query()->with($with)->find($id);
    }

    public function create(array $data): Diagnosis
    {
        return Diagnosis::query()->create($data);
    }

    public function update(Diagnosis $diagnosis, array $data): Diagnosis
    {
        $diagnosis->update($data);

        return $diagnosis->refresh();
    }

    public function delete(Diagnosis $diagnosis): void
    {
        $diagnosis->delete();
    }
}
