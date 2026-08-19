<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TreatmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tratamiento/servicio del catalogo de la clinica, con su valor. Aislado por
 * tenant. es_diferencial marca la atencion diferencial / tratamiento no listado.
 *
 * 'categoria' es texto libre, solo descriptivo (agrupa la ficha rica del
 * catalogo publico). La relacion real con el catalogo de especialidades es
 * especialidad_id (FK): un tratamiento pertenece a una sola especialidad, o
 * ninguna.
 */
class Treatment extends Model
{
    /** @use HasFactory<TreatmentFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'nombre',
        'categoria',
        'especialidad_id',
        'slug',
        'descripcion',
        'incluye',
        'precio',
        'duracion_minutos',
        'es_diferencial',
        'activo',
    ];

    /**
     * @return BelongsTo<Especialidad, $this>
     */
    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class);
    }

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'duracion_minutos' => 'integer',
            'es_diferencial' => 'boolean',
            'activo' => 'boolean',
            'incluye' => 'array',
        ];
    }
}
