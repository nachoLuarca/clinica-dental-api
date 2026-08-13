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
            'professional_id' => ['required', 'integer'],
            'treatment_id' => ['required', 'integer'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
