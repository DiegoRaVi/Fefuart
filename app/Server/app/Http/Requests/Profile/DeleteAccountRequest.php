<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D22 — suprimir la cuenta exige la contrasena.
 *
 * Es irreversible y se lleva por delante las fotos que subio, asi que no
 * puede dispararse desde una sesion que alguien se dejo abierta en un
 * ordenador prestado. `current_password` la comprueba contra el guard, no
 * contra el cuerpo.
 */
class DeleteAccountRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Sin guard explicito, como `UpdatePasswordRequest`: comprueba
            // contra el usuario autenticado, no contra un valor del cuerpo.
            'password' => ['required', 'string', 'current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['password' => 'contrasena'];
    }
}
