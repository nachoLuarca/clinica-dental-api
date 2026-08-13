<?php

namespace App\Http\Requests\Staff;

use App\Models\Budget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edicion de presupuesto. Se puede cambiar solo el estado/notas, o tambien
 * reemplazar las lineas (si vienen 'items', el servicio recalcula el total).
 */
class BudgetUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['sometimes', Rule::in(Budget::ESTADOS)],
            'notas' => ['nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.treatment_id' => ['nullable', 'integer'],
            'items.*.nombre' => ['nullable', 'string', 'max:255'],
            'items.*.precio_unitario' => ['nullable', 'numeric', 'min:0'],
            'items.*.cantidad' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
