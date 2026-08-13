<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProfessionalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Profesional de una clinica. Modelo aislado por tenant.
 *
 * Introducido en el paso 2 como primer modelo tenant-scoped; en el paso 4 se le
 * completa el CRUD y la configuracion de horarios de atencion (schedules).
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

    /**
     * Horarios de atencion (disponibilidad base) del profesional.
     *
     * @return HasMany<ProfessionalSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(ProfessionalSchedule::class);
    }

    /**
     * @return HasMany<Diagnosis, $this>
     */
    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
