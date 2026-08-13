<?php

namespace App\Tenancy;

/**
 * Mantiene el tenant activo durante el ciclo de vida del request.
 *
 * Se registra como singleton en el contenedor. El middleware ResolveTenant
 * es el unico responsable de poblarlo (a partir del usuario autenticado);
 * el resto de la aplicacion solo lo lee a traves del TenantScope y del trait
 * BelongsToTenant.
 */
class TenantContext
{
    protected ?int $tenantId = null;

    public function setTenantId(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function tenantId(): ?int
    {
        return $this->tenantId;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    public function forget(): void
    {
        $this->tenantId = null;
    }

    /**
     * Ejecuta un callback con un tenant fijado temporalmente y restaura el
     * tenant previo al terminar. Util para jobs en cola, seeders y tests.
     *
     * @template T
     * @param  callable():T  $callback
     * @return T
     */
    public function runWithTenant(int $tenantId, callable $callback): mixed
    {
        $previous = $this->tenantId;
        $this->tenantId = $tenantId;

        try {
            return $callback();
        } finally {
            $this->tenantId = $previous;
        }
    }
}
