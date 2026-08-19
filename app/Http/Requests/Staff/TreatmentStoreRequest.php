<?php

namespace App\Http\Requests\Staff;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TreatmentStoreRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            // Texto libre, solo descriptivo (agrupa la ficha rica del
            // catalogo publico). La relacion real es especialidad_id.
            'categoria' => ['nullable', 'string', 'max:255'],
            'especialidad_id' => [
                'nullable', 'integer',
                Rule::exists('especialidades', 'id')->where('tenant_id', app(TenantContext::class)->tenantId()),
            ],
            'descripcion' => ['nullable', 'string'],
            // Lo que trae la sesion (ficha rica del catalogo publico).
            'incluye' => ['sometimes', 'nullable', 'array'],
            'incluye.*' => ['string', 'max:255'],
            'precio' => ['required', 'numeric', 'min:0'],
            'duracion_minutos' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'es_diferencial' => ['boolean'],
            'activo' => ['boolean'],
        ];
    }
}
