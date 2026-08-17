<?php

namespace App\Services;

use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Datos de marca de la clinica (nombre, logo, color) para el propio tenant
 * autenticado. Siempre opera sobre el tenant del TenantContext, nunca sobre
 * un id que mande el cliente.
 */
class TenantService
{
    private const LOGO_DISK = 'public';

    private const LOGO_DIR = 'logos';

    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly TenantContext $context,
    ) {}

    public function current(): Tenant
    {
        $tenantId = $this->context->tenantId();

        return ($tenantId !== null ? $this->tenants->findById($tenantId) : null)
            ?? throw (new ModelNotFoundException)->setModel(Tenant::class);
    }

    /**
     * @param  array{nombre?: string, color_primario?: ?string}  $data
     */
    public function update(array $data, ?UploadedFile $logo = null): Tenant
    {
        $tenant = $this->current();

        if ($logo !== null) {
            $this->eliminarLogoAnterior($tenant);
            $data['logo_path'] = $logo->store(self::LOGO_DIR, self::LOGO_DISK);
        }

        return $this->tenants->update($tenant, $data);
    }

    private function eliminarLogoAnterior(Tenant $tenant): void
    {
        if ($tenant->logo_path !== null) {
            Storage::disk(self::LOGO_DISK)->delete($tenant->logo_path);
        }
    }
}
