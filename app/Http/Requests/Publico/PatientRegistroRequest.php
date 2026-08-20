<?php

namespace App\Http\Requests\Publico;

use App\Models\Patient;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de paciente sin login (paso de Identificacion del flujo de reserva
 * publico, cuando el RUT no existia): sin password, sin token de sesion. El
 * login/cuenta con contrasena queda para una etapa futura del proyecto.
 */
class PatientRegistroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El rut se guarda normalizado (Patient::rut() mutator, sin puntos). Sin
     * normalizarlo aca tambien, el rule 'unique' de abajo compara contra el
     * valor crudo que mando el cliente y no detecta un duplicado que difiera
     * solo en formato (con/sin puntos) -el conflicto real explota recien al
     * insertar, como violacion de constraint en vez de error de validacion.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('rut')) {
            $this->merge(['rut' => Patient::normalizarRut((string) $this->input('rut'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'rut' => [
                'required', 'string', 'max:20', 'regex:/^\d{1,2}\.?\d{3}\.?\d{3}-?[\dkK]$/',
                Rule::unique('patients', 'rut')->where('tenant_id', $tenantId),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('patients', 'email')->where('tenant_id', $tenantId),
            ],
            'telefono' => ['required', 'string', 'max:50'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            // Checkbox obligatorio de aceptacion de tratamiento de datos
            // personales: debe venir en true, no solo estar presente.
            'acepta_tratamiento_datos' => ['required', 'accepted'],
        ];
    }
}
