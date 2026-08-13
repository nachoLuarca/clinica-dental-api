<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Registro clinico de un paciente por el staff. La password es OPCIONAL: el
 * staff puede pre-registrar la ficha sin credenciales de acceso.
 */
class PatientStoreRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'fecha_nacimiento' => ['required', 'date'],
            'notas' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }
}
