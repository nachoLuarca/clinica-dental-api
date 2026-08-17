<?php

namespace App\Repositories\Contracts;

use App\Models\Patient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PatientRepositoryInterface
{
    public function findByTenantAndEmail(int $tenantId, string $email): ?Patient;

    public function findByTenantAndRut(int $tenantId, string $rut): ?Patient;

    /**
     * Para el lookup publico sin login: RUT + fecha de nacimiento como
     * segundo factor (el RUT solo no alcanza, ver Publico\PatientAppointmentLookupController).
     */
    public function findByTenantRutAndBirthdate(int $tenantId, string $rut, string $fechaNacimiento): ?Patient;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Patient;

    /**
     * Listado paginado dentro del tenant activo (registro clinico del staff).
     *
     * @param  array<int, string>  $with
     * @return LengthAwarePaginator<int, Patient>
     */
    public function paginate(int $perPage = 15, array $with = []): LengthAwarePaginator;

    /**
     * @param  array<int, string>  $with
     */
    public function find(int $id, array $with = []): ?Patient;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Patient $patient, array $data): Patient;

    public function delete(Patient $patient): void;
}
