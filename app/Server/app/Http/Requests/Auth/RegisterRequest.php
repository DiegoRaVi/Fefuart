<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * SEC-001: `role` no figura en las reglas y tampoco es fillable en el
     * modelo. `validated()` solo devuelve estas tres claves, asi que un
     * `"role": "admin"` en el cuerpo se descarta sin llegar al modelo.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            // v1 exigia min:10 en el email, lo que rechazaba direcciones
            // cortas perfectamente validas (a@b.es).
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // v1 aceptaba contrasenas de 5 caracteres. Password::defaults()
            // exige 8 y permite endurecerlo mas adelante en un solo sitio.
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }
}
