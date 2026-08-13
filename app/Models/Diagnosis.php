<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DiagnosisFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Diagnostico clinico de un paciente emitido por un profesional. Parte de la
 * ficha clinica gestionada por el staff. Aislado por tenant.
 */
class Diagnosis extends Model
{
    /** @use HasFactory<DiagnosisFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'patient_id',
        'professional_id',
        'fecha',
        'descripcion',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Professional, $this>
     */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
