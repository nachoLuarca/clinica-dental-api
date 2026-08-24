<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvenioUpdateRequest extends FormRequest
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
            'tipo' => ['sometimes', 'required', 'string', Rule::in(['fonasa', 'isapre', 'caja_compensacion', 'aseguradora', 'otro'])],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'activo' => ['boolean'],
            'logo' => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
        ];
    }
}
