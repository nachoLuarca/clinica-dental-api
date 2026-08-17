<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class TenantUpdateRequest extends FormRequest
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
            'color_primario' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            // Sin la regla 'image': usa getimagesize(), que no reconoce SVG y
            // rechazaba cualquier logo .svg pese a estar en 'mimes'. 'mimes'
            // solo (extension + tipo MIME real via finfo) ya cubre los 5
            // formatos sin ese problema.
            'logo' => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
        ];
    }
}
