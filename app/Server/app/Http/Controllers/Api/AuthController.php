<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * SEC-001: el rol lo fija el servidor, siempre. En v1 se leia del cuerpo
     * de la peticion, de modo que un `"role": "admin"` contra este endpoint
     * publico bastaba para tomar el control del backoffice.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = new User($request->validated());
        $user->role_id = UserRole::default();
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        /*
         * N19 — el correo de verificacion sale del alta, no de que alguien
         * descubra el boton de reenviar.
         *
         * Faltaba desde la Fase 1 y lo encontro el E2E: la bateria cubria el
         * reenvio manual y el cambio de correo desde el perfil, asi que
         * `sendEmailVerificationNotification()` estaba probado en los dos
         * sitios donde no hacia falta y en ninguno donde si.
         *
         * Se llama directo y no por el evento `Registered`: es como lo hacen
         * ProfileController y EmailVerificationController, y no depende de
         * que el listener este descubierto.
         */
        $user->sendEmailVerificationNotification();

        return UserResource::make($user)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * La validacion de credenciales y el limitador viven en LoginRequest.
     */
    public function login(LoginRequest $request): UserResource
    {
        $request->authenticate();

        // Rota el id de sesion tras autenticar: sin esto un id capturado
        // antes del login seguiria siendo valido despues (session fixation).
        $request->session()->regenerate();

        return UserResource::make($request->user());
    }

    /**
     * SEC-011: con JWT el logout solo invalidaba el token presentado y no
     * habia forma de cerrar sesion en otro dispositivo. Al destruir la
     * sesion la cookie deja de servir de inmediato.
     */
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
