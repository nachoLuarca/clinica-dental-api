<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cita/reserva de un paciente con un profesional para un tratamiento. Aislada
 * por tenant.
 *
 * fecha_hora es el inicio; fecha_hora_fin el fin (derivado de la duracion del
 * tratamiento al momento de reservar). El estado 'cancelada' libera el slot: el
 * indice unico parcial (professional_id + fecha_hora) solo aplica a citas no
 * canceladas, y el calculo de disponibilidad ignora las canceladas.
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use BelongsToTenant, HasFactory;

    /** Estados validos de la cita. */
    public const ESTADO_RESERVADA = 'reservada';

    public const ESTADO_CONFIRMADA = 'confirmada';

    public const ESTADO_CANCELADA = 'cancelada';

    public const ESTADO_COMPLETADA = 'completada';

    public const ESTADOS = [
        self::ESTADO_RESERVADA,
        self::ESTADO_CONFIRMADA,
        self::ESTADO_CANCELADA,
        self::ESTADO_COMPLETADA,
    ];

    protected $fillable = [
        'professional_id',
        'patient_id',
        'treatment_id',
        'fecha_hora',
        'fecha_hora_fin',
        'duracion_minutos',
        'estado',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'fecha_hora_fin' => 'datetime',
            'duracion_minutos' => 'integer',
        ];
    }

    public function isCancelada(): bool
    {
        return $this->estado === self::ESTADO_CANCELADA;
    }

    /**
     * @return BelongsTo<Professional, $this>
     */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Treatment, $this>
     */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }
}
