<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvenioStoreRequest extends FormRequest
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
            'tipo' => ['required', 'string', Rule::in(['fonasa', 'isapre', 'caja_compensacion', 'aseguradora', 'otro'])],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'activo' => ['boolean'],
            // Mismas reglas que el logo de la clinica (TenantUpdateRequest):
            // sin 'image' porque getimagesize() no reconoce SVG.
            'logo' => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
        ];
    }
}
