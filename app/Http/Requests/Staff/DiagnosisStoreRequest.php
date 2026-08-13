<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class DiagnosisStoreRequest extends FormRequest
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
        // La pertenencia del profesional al tenant se valida en el servicio
        // (via repositorio scopeado), no por 'exists' que ignoraria el tenant.
        return [
            'professional_id' => ['nullable', 'integer'],
            'fecha' => ['required', 'date'],
            'descripcion' => ['required', 'string'],
            'notas' => ['nullable', 'string'],
        ];
    }
}
