<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tramo horario de atencion de un profesional para un dia de la semana.
 *
 * Estructura base de disponibilidad; el calculo de slots libres (cruzando con
 * citas) es del paso 5. Aislado por tenant.
 */
class ProfessionalSchedule extends Model
{
    /** @use HasFactory<\Database\Factories\ProfessionalScheduleFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'professional_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Professional, $this>
     */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
