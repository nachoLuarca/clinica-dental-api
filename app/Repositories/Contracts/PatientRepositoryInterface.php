<?php

namespace App\Repositories\Contracts;

use App\Models\Patient;

interface PatientRepositoryInterface
{
    public function findByTenantAndEmail(int $tenantId, string $email): ?Patient;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Patient;
}
