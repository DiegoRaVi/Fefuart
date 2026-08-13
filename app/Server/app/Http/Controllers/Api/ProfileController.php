<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * N19: v1 no tenia pantalla de perfil. Un cliente que se equivocaba al
 * escribir su nombre o su direccion de correo tenia que escribir a Felicitas.
 */
class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->fill($request->validated());

        // Cambiar de correo invalida la verificacion: la direccion nueva no
        // esta comprobada todavia, y a ella iran los avisos de pedido y los
        // enlaces de descarga.
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($user->wasChanged('email')) {
            $user->sendEmailVerificationNotification();
        }

        return UserResource::make($user);
    }

    /**
     * Exige la contrasena actual: sin eso, una sesion secuestrada bastaria
     * para expulsar al dueno de su propia cuenta.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        // Invalida el resto de sesiones abiertas con la contrasena anterior.
        // Recibe la contrasena NUEVA: el metodo la valida contra el hash ya
        // almacenado, que en este punto es el que se acaba de guardar.
        Auth::guard('web')->logoutOtherDevices($request->validated('password'));

        return response()->json(['message' => 'Contrasena actualizada.']);
    }
}
