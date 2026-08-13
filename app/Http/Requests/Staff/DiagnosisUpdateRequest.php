<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class DiagnosisUpdateRequest extends FormRequest
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
            'professional_id' => ['nullable', 'integer'],
            'fecha' => ['sometimes', 'required', 'date'],
            'descripcion' => ['sometimes', 'required', 'string'],
            'notas' => ['nullable', 'string'],
        ];
    }
}
