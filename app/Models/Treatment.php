<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tratamiento/servicio del catalogo de la clinica, con su valor. Aislado por
 * tenant. es_diferencial marca la atencion diferencial / tratamiento no listado.
 */
class Treatment extends Model
{
    /** @use HasFactory<\Database\Factories\TreatmentFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'precio',
        'es_diferencial',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'es_diferencial' => 'boolean',
            'activo' => 'boolean',
        ];
    }
}
