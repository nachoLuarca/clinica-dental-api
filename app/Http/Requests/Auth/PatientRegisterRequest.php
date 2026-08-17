<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class PatientRegisterRequest extends FormRequest
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
            'clinica' => ['required', 'string', 'max:255'],
            'nombre' => ['required', 'string', 'max:255'],
            // Requerido en el auto-registro (no en el alta hecha por staff):
            // sin RUT el paciente no puede usar despues la gestion publica de
            // citas sin login (RUT + fecha_nacimiento).
            'rut' => ['required', 'string', 'max:20', 'regex:/^\d{1,2}\.?\d{3}\.?\d{3}-?[\dkK]$/'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
        ];
    }
}
