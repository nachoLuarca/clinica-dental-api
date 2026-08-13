<?php

namespace App\Http\Requests\Staff;

use App\Models\Budget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de presupuesto. El total NO se acepta (lo calcula el servicio). Cada
 * linea referencia un tratamiento o es diferencial (nombre/precio libres); la
 * combinacion la termina de validar el servicio.
 */
class BudgetStoreRequest extends FormRequest
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
            'patient_id' => ['required', 'integer'],
            'estado' => ['sometimes', Rule::in(Budget::ESTADOS)],
            'notas' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.treatment_id' => ['nullable', 'integer'],
            'items.*.nombre' => ['nullable', 'string', 'max:255'],
            'items.*.precio_unitario' => ['nullable', 'numeric', 'min:0'],
            'items.*.cantidad' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
