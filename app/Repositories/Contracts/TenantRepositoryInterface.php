<?php

namespace App\Repositories\Contracts;

use App\Models\Tenant;

interface TenantRepositoryInterface
{
    public function findBySlug(string $slug): ?Tenant;

    public function findById(int $id): ?Tenant;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tenant $tenant, array $data): Tenant;
}
