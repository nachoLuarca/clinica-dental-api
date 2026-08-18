<?php

namespace App\Http\Requests\Staff;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EspecialidadUpdateRequest extends FormRequest
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
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('especialidades', 'nombre')
                    ->where('tenant_id', app(TenantContext::class)->tenantId())
                    ->ignore($this->route('especialidad')),
            ],

            'categorias' => ['sometimes', 'array'],
            'categorias.*' => ['string', 'max:255'],
        ];
    }
}
