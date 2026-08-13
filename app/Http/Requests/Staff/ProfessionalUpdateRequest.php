<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class ProfessionalUpdateRequest extends FormRequest
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
            'apellido' => ['nullable', 'string', 'max:255'],
            'especialidad' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'activo' => ['boolean'],

            'horarios' => ['sometimes', 'array'],
            'horarios.*.dia_semana' => ['required_with:horarios', 'integer', 'between:0,6'],
            'horarios.*.hora_inicio' => ['required_with:horarios', 'date_format:H:i'],
            'horarios.*.hora_fin' => ['required_with:horarios', 'date_format:H:i', 'after:horarios.*.hora_inicio'],
        ];
    }
}
