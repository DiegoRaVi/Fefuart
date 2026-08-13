<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * N19: v1 no tenia recuperacion de contrasena pese a que la tabla
 * `password_reset_tokens` existia desde la primera migracion. Quien olvidaba
 * su contrasena perdia el acceso y su historial de pedidos para siempre.
 */
class PasswordResetController extends Controller
{
    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->validated());

        // Respuesta identica exista o no la cuenta: distinguirlas convertiria
        // este endpoint en un oraculo de que emails estan registrados, el
        // mismo problema que se evita en el login.
        if (in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER], true)) {
            return response()->json([
                'message' => 'Si esa direccion tiene una cuenta, recibiras un correo con las instrucciones.',
            ]);
        }

        throw ValidationException::withMessages(['email' => __($status)]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->validated(),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => 'Contrasena actualizada.']);
    }
}
