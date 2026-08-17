<?php

namespace App\Http\Requests\Publico;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RUT + fecha de nacimiento: el par que identifica al paciente sin login.
 * El RUT solo no alcanza (es un dato demasiado adivinable/conocido); pedir
 * el segundo factor evita que cualquiera con el RUT de otra persona vea o
 * cancele sus citas.
 */
class PatientLookupRequest extends FormRequest
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
            'rut' => ['required', 'string', 'max:20'],
            'fecha_nacimiento' => ['required', 'date'],
        ];
    }
}
