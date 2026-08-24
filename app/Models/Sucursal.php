<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sede fisica de una clinica. Aislada por tenant: cada clinica arma su
 * propio listado de sucursales (una o mas).
 */
class Sucursal extends Model
{
    use BelongsToTenant;

    protected $table = 'sucursales';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'direccion',
        'comuna',
        'telefono',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * Horario de atencion (puede variar por dia).
     *
     * @return HasMany<SucursalSchedule, $this>
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(SucursalSchedule::class);
    }

    /**
     * Profesionales que atienden en esta sede.
     *
     * @return HasMany<Professional, $this>
     */
    public function professionals(): HasMany
    {
        return $this->hasMany(Professional::class);
    }
}
