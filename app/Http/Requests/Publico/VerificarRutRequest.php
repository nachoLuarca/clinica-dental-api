<?php

namespace App\Http\Requests\Publico;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paso 1 del flujo de reserva publico (Identificacion por RUT): confirma si
 * el RUT ya pertenece a un paciente de la clinica. Protegido con Turnstile
 * en vez de un segundo factor (RUT+fecha_nacimiento) como el resto del
 * acceso publico sin login, porque el propio punto de este paso es
 * resolver la identidad con SOLO el RUT.
 */
class VerificarRutRequest extends FormRequest
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
            'rut' => ['required', 'string', 'max:20', 'regex:/^\d{1,2}\.?\d{3}\.?\d{3}-?[\dkK]$/'],
            'turnstile_token' => ['required', 'string'],
        ];
    }
}
