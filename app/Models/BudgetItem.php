<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linea de un presupuesto. Referencia un tratamiento del catalogo o es una
 * atencion diferencial (treatment_id null, nombre/precio libres). Guarda un
 * snapshot de nombre y precio para no depender de cambios posteriores en el
 * catalogo. Aislado por tenant.
 */
class BudgetItem extends Model
{
    /** @use HasFactory<\Database\Factories\BudgetItemFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'budget_id',
        'treatment_id',
        'nombre',
        'precio_unitario',
        'cantidad',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'cantidad' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return BelongsTo<Treatment, $this>
     */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }
}
