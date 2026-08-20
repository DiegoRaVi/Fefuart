<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * SEC-007: v1 no aplicaba ningun limitador al login, asi que la fuerza
     * bruta solo estaba acotada por el coste de bcrypt.
     *
     * La clave combina email e IP: limitar solo por IP castiga a usuarios
     * legitimos tras una NAT compartida, y limitar solo por email permite
     * bloquear la cuenta de otro a voluntad.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            // Mensaje unico para credenciales invalidas: distinguir "el email
            // no existe" de "la contrasena no coincide" convierte el login en
            // un oraculo de que cuentas existen.
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /*
         * D21 — una cuenta desactivada no entra.
         *
         * Se comprueba **despues** de validar la contrasena y no antes: si se
         * mirase primero, responder «esta cuenta esta desactivada» a
         * cualquiera que escriba un email seria decirle que esa cuenta
         * existe. Es la misma razon por la que el mensaje de credenciales
         * invalidas es unico.
         *
         * Cubre tambien a las cuentas suprimidas (D22), que quedan
         * desactivadas: alli ya no hay nadie detras.
         */
        if (Auth::user()->deactivated_at !== null) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'Esta cuenta esta desactivada. Escribenos si quieres recuperarla.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower((string) $this->input('email')).'|'.$this->ip()
        );
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }
}
