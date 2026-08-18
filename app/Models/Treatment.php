<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TreatmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tratamiento/servicio del catalogo de la clinica, con su valor. Aislado por
 * tenant. es_diferencial marca la atencion diferencial / tratamiento no listado.
 */
class Treatment extends Model
{
    /** @use HasFactory<TreatmentFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'nombre',
        'categoria',
        'slug',
        'descripcion',
        'incluye',
        'precio',
        'duracion_minutos',
        'es_diferencial',
        'activo',
    ];

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
