<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class TreatmentUpdateRequest extends FormRequest
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
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'precio' => ['sometimes', 'required', 'numeric', 'min:0'],
            'duracion_minutos' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'es_diferencial' => ['boolean'],
            'activo' => ['boolean'],
        ];
    }
}
