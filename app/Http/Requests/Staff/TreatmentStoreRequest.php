<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class TreatmentStoreRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            // Lo que trae la sesion (ficha rica del catalogo publico).
            'incluye' => ['sometimes', 'nullable', 'array'],
            'incluye.*' => ['string', 'max:255'],
            'precio' => ['required', 'numeric', 'min:0'],
            'duracion_minutos' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'es_diferencial' => ['boolean'],
            'activo' => ['boolean'],
        ];
    }
}
