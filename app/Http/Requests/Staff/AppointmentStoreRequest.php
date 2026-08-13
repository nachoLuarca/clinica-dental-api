<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de cita desde el portal staff: el staff reserva para un paciente de su
 * clinica, por lo que patient_id es obligatorio. La pertenencia al tenant de
 * paciente/profesional/tratamiento la valida el servicio via repositorios.
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
            'patient_id' => ['required', 'integer'],
            'professional_id' => ['required', 'integer'],
            'treatment_id' => ['required', 'integer'],
            'fecha_hora' => ['required', 'date'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
