<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // No se valida contra la tabla roles aca: el nombre debe existir
            // para ESTE tenant especificamente, y eso solo lo sabe el servicio
            // (Role no tiene global scope propio). Si no existe, el servicio
            // tira 404, no 422 -no se confirma la existencia de roles ajenos.
            'rol' => ['required', 'string'],
        ];
    }
}
