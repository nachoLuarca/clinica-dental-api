<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la consulta de disponibilidad (query params). La existencia del
 * profesional/tratamiento/especialidad dentro del tenant la verifica el
 * servicio (via repositorios ya filtrados por TenantScope), no una regla
 * 'exists' global.
 *
 * treatment_id vs especialidad_id: con professional_id (reserva de un
 * profesional puntual), treatment_id sigue siendo obligatorio -la duracion
 * del slot es la del tratamiento elegido, sin ambiguedad-. Sin
 * professional_id (wizard con entry point Especialidad/Sucursal, sin
 * tratamiento puntual todavia), alguno de los dos es obligatorio pero no
 * ambos a la vez (ver withValidator).
 */
class AvailabilityRequest extends FormRequest
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
            // Opcional: sin professional_id, se agregan los slots libres de
            // TODOS los profesionales activos (modo "cualquiera disponible").
            'professional_id' => ['sometimes', 'nullable', 'integer'],
            // Opcional, solo tiene efecto en modo "cualquiera disponible"
            // (sin professional_id): acota los profesionales agregados a una
            // sede (wizard con entry point Sucursal).
            'sucursal_id' => ['sometimes', 'nullable', 'integer'],
            'treatment_id' => ['sometimes', 'nullable', 'integer'],
            'especialidad_id' => ['sometimes', 'nullable', 'integer'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tieneProfesional = $this->filled('professional_id');
            $tieneTreatment = $this->filled('treatment_id');
            $tieneEspecialidad = $this->filled('especialidad_id');

            if ($tieneProfesional && ! $tieneTreatment) {
                $validator->errors()->add('treatment_id', 'El campo treatment_id es obligatorio cuando se especifica professional_id.');

                return;
            }

            if (! $tieneProfesional) {
                if (! $tieneTreatment && ! $tieneEspecialidad) {
                    $validator->errors()->add('treatment_id', 'Debes indicar treatment_id o especialidad_id.');
                }

                if ($tieneTreatment && $tieneEspecialidad) {
                    $validator->errors()->add('especialidad_id', 'No puedes indicar treatment_id y especialidad_id a la vez.');
                }
            }
        });
    }
}
