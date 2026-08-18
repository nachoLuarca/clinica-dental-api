<?php

namespace App\Http\Requests\Paciente;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de cita desde el sitio del paciente: el paciente reserva SIEMPRE para si
 * mismo, por eso NO se acepta patient_id del cliente (lo fija el controller con
 * el id del paciente autenticado), igual criterio que el tenant_id.
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
            // Opcional: sin professional_id, el servicio auto-asigna el
            // primer profesional activo que tenga ese horario libre (modo
            // "cualquiera disponible").
            'professional_id' => ['sometimes', 'nullable', 'integer'],
            'treatment_id' => ['required', 'integer'],
            'fecha_hora' => ['required', 'date'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
