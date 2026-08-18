<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una categoria de tratamiento (Treatment::categoria) que una especialidad
 * puede cubrir. Sin tenant_id propio: hereda el aislamiento de
 * especialidad_id (BelongsToTenant en Especialidad).
 */
class EspecialidadCategoria extends Model
{
    protected $table = 'especialidad_categoria';

    protected $fillable = [
        'especialidad_id',
        'categoria',
    ];

    /**
     * @return BelongsTo<Especialidad, $this>
     */
    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class);
    }
}
