<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class SucursalStoreRequest extends FormRequest
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
            'direccion' => ['nullable', 'string', 'max:255'],
            'comuna' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'activo' => ['boolean'],

            // Horario de atencion (opcional, puede variar por dia). Reemplaza
            // por completo los tramos, mismo criterio que 'horarios' de
            // ProfessionalStoreRequest.
            'horarios' => ['sometimes', 'array'],
            'horarios.*.dia_semana' => ['required_with:horarios', 'integer', 'between:0,6'],
            'horarios.*.hora_inicio' => ['required_with:horarios', 'date_format:H:i'],
            'horarios.*.hora_fin' => ['required_with:horarios', 'date_format:H:i', 'after:horarios.*.hora_inicio'],
        ];
    }
}
