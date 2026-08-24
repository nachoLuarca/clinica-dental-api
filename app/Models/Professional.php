<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProfessionalFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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
        'tenant_id',
        'nombre',
        'apellido',
        'especialidad',
        'email',
        'activo',
        'sucursal_id',
        'foto_path',
        'bio',
        'matricula',
    ];

    protected $appends = ['foto_url'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * URL publica de la foto (disco 'public', servido via /storage). Null
     * si el profesional todavia no tiene una cargada. Mismo patron que
     * Tenant::logoUrl().
     */
    protected function fotoUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->foto_path !== null
                ? Storage::disk('public')->url($this->foto_path)
                : null,
        );
    }

    /**
     * Sede a la que pertenece (etapa 1: una sola). Nullable: clinicas que
     * todavia no cargaron sucursales siguen funcionando igual.
     *
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
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
     * Especialidades formales asignadas (paso 11: filtro de reserva por
     * categoria de tratamiento). Distintas del campo libre 'especialidad'
     * (texto historico, solo presentacion): esta relacion es la que se usa
     * para filtrar. Un profesional puede tener mas de una.
     *
     * @return BelongsToMany<Especialidad, $this>
     */
    public function especialidades(): BelongsToMany
    {
        return $this->belongsToMany(Especialidad::class, 'professional_especialidad')->withTimestamps();
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
