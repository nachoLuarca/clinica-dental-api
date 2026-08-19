<?php

namespace App\Http\Requests\Staff;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EspecialidadStoreRequest extends FormRequest
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
            'nombre' => [
                'required', 'string', 'max:255',
                Rule::unique('especialidades', 'nombre')->where('tenant_id', app(TenantContext::class)->tenantId()),
            ],

            // Tratamientos que cubre esta especialidad (FK real). Opcional;
            // reemplaza por completo el set actual (ver
            // TreatmentRepository::syncEspecialidad).
            'treatment_ids' => ['sometimes', 'array'],
            'treatment_ids.*' => [
                'integer',
                Rule::exists('treatments', 'id')->where('tenant_id', app(TenantContext::class)->tenantId()),
            ],
        ];
    }
}
