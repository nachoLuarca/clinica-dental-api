<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
#[Fillable(['tenant_id', 'nombre', 'rut', 'email', 'telefono', 'password', 'fecha_nacimiento', 'notas'])]
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

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Sin puntos y con el digito verificador en mayuscula: "12.345.678-9" y
     * "12345678-9" deben matchear al mismo paciente en el lookup publico por
     * RUT, sin importar como lo haya tipeado cada vez.
     */
    public static function normalizarRut(string $rut): string
    {
        return strtoupper(str_replace('.', '', trim($rut)));
    }

    /**
     * Mutator: normaliza el RUT SIEMPRE que se guarde, sin importar el
     * camino (servicio, factory, tinker, seeder). Sin esto, alguien que
     * cree un paciente pasando el RUT tal cual (con puntos) queda
     * inalcanzable para el lookup publico, que si normaliza el que recibe.
     */
    protected function rut(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => $value !== null ? self::normalizarRut($value) : null);
    }
}
