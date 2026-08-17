<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StaffRepositoryInterface
{
    public function findByTenantAndEmail(int $tenantId, string $email): ?User;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User;

    /**
     * @param  array{rol?: string, activo?: bool, nombre?: string}  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?User;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User;

    /**
     * Cuenta el staff ACTIVO (excluyendo a $excludeUserId) que tiene el
     * permiso dado via alguno de sus roles. Usado para la salvaguarda de
     * "ultimo admin" (nunca dejar la clinica sin nadie que pueda gestionar
     * roles/usuarios).
     */
    public function countActivosConPermiso(string $permission, int $excludeUserId): int;
}
