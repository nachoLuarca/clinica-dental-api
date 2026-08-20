<?php

namespace App\Http\Requests\Publico;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de cita SIN login (paso Confirmar del flujo de reserva publico): el
 * paciente ya quedo identificado por RUT en el paso 1 (Identificacion). Se
 * vuelve a exigir Turnstile aca -no se reusa el del paso 1, son de un solo
 * uso y expiran en minutos- porque este endpoint crea una cita real, a
 * diferencia de verificar-rut que solo consulta.
 */
class AppointmentStoreRequest extends FormRequest
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

            // Opcional: sin professional_id, el servicio auto-asigna el
            // primer profesional activo que tenga ese horario libre (modo
            // "cualquiera disponible"), igual que en Paciente\AppointmentController.
            'professional_id' => ['sometimes', 'nullable', 'integer'],
            'treatment_id' => ['required', 'integer'],
            'fecha_hora' => ['required', 'date'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
