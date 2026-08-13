<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProfessionalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Profesional de una clinica. Modelo aislado por tenant.
 *
 * Se introduce en el paso 2 como primer modelo tenant-scoped para ejercitar y
 * verificar el aislamiento; el CRUD completo se desarrolla en el paso 4.
 */
class Professional extends Model
{
    /** @use HasFactory<ProfessionalFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'nombre',
        'apellido',
        'especialidad',
        'email',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
