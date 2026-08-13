<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class PatientUpdateRequest extends FormRequest
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
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'fecha_nacimiento' => ['sometimes', 'required', 'date'],
            'notas' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }
}
