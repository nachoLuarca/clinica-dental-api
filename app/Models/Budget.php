<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Presupuesto: propuesta de tratamientos con estado y total, previa a las citas.
 * Pertenece a un paciente y agrupa lineas (BudgetItem). Aislado por tenant.
 *
 * El total lo recalcula siempre el servicio a partir de las lineas.
 */
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use BelongsToTenant, HasFactory;

    /** Estados validos del presupuesto. */
    public const ESTADOS = ['borrador', 'enviado', 'aceptado', 'rechazado'];

    protected $fillable = [
        'patient_id',
        'estado',
        'total',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
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
     * @return HasMany<BudgetItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }
}
