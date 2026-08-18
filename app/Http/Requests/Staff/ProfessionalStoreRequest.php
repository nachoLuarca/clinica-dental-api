<?php

namespace App\Http\Requests\Staff;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfessionalStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorizacion la resuelve el middleware 'auth:staff' + 'tenant'.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['nullable', 'string', 'max:255'],
            'especialidad' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'activo' => ['boolean'],

            // Horarios de atencion (opcional). Reemplazan por completo los tramos.
            'horarios' => ['sometimes', 'array'],
            'horarios.*.dia_semana' => ['required_with:horarios', 'integer', 'between:0,6'],
            'horarios.*.hora_inicio' => ['required_with:horarios', 'date_format:H:i'],
            'horarios.*.hora_fin' => ['required_with:horarios', 'date_format:H:i', 'after:horarios.*.hora_inicio'],

            // Especialidades formales (paso 11), opcional. Reemplazan por
            // completo las asignadas. IDs deben pertenecer al tenant activo.
            'especialidades' => ['sometimes', 'array'],
            'especialidades.*' => [
                'integer',
                Rule::exists('especialidades', 'id')->where('tenant_id', app(TenantContext::class)->tenantId()),
            ],
        ];
    }
}
