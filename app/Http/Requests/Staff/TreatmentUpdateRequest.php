<?php

namespace App\Http\Requests\Staff;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TreatmentUpdateRequest extends FormRequest
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
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'especialidad_id' => [
                'nullable', 'integer',
                Rule::exists('especialidades', 'id')->where('tenant_id', app(TenantContext::class)->tenantId()),
            ],
            'descripcion' => ['nullable', 'string'],
            'incluye' => ['sometimes', 'nullable', 'array'],
            'incluye.*' => ['string', 'max:255'],
            'precio' => ['sometimes', 'required', 'numeric', 'min:0'],
            'duracion_minutos' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'es_diferencial' => ['boolean'],
            'activo' => ['boolean'],
        ];
    }
}
