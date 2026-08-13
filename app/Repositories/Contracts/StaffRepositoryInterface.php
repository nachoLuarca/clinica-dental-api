<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface StaffRepositoryInterface
{
    public function findByTenantAndEmail(int $tenantId, string $email): ?User;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User;
}
