<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Paciente de una clinica (guard 'paciente'). Aislado por tenant.
 *
 * Autentica exclusivamente por token Sanctum a traves del provider 'pacientes'.
 * Un token de staff nunca resuelve a este modelo, y viceversa: los dos guards
 * son independientes.
 */
#[Fillable(['tenant_id', 'nombre', 'email', 'telefono', 'password', 'fecha_nacimiento', 'notas'])]
#[Hidden(['password', 'remember_token'])]
class Patient extends Authenticatable
{
    /** @use HasFactory<PatientFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, Notifiable;

    protected $table = 'patients';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Diagnosticos clinicos del paciente (historial), a cargo del staff.
     *
     * @return HasMany<Diagnosis, $this>
     */
    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    /**
     * @return HasMany<Budget, $this>
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }
}
