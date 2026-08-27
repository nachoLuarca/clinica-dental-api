<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la consulta de disponibilidad (query params). La existencia del
 * profesional/tratamiento dentro del tenant la verifica el servicio (via
 * repositorios ya filtrados por TenantScope), no una regla 'exists' global.
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
            'treatment_id' => ['required', 'integer'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
