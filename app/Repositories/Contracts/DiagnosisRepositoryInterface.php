<?php

namespace App\Repositories\Contracts;

use App\Models\Diagnosis;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DiagnosisRepositoryInterface
{
    /**
     * Listado paginado de diagnosticos de un paciente dentro del tenant activo.
     *
     * @param  array<int, string>  $with
     * @return LengthAwarePaginator<int, Diagnosis>
     */
    public function paginateForPatient(int $patientId, int $perPage = 15, array $with = []): LengthAwarePaginator;

    /**
     * @param  array<int, string>  $with
     */
    public function find(int $id, array $with = []): ?Diagnosis;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Diagnosis;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Diagnosis $diagnosis, array $data): Diagnosis;

    public function delete(Diagnosis $diagnosis): void;
}
