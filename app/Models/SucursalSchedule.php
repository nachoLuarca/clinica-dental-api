<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tramo horario de atencion de una sucursal para un dia de la semana.
 * Mismo esquema que ProfessionalSchedule. Aislado por tenant.
 */
class SucursalSchedule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'sucursal_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
}
