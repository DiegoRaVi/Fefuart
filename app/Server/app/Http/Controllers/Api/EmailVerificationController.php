<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * N19: la columna `email_verified_at` existia en v1 desde la primera
 * migracion y nunca se uso. Importa porque a esa direccion se enviaran los
 * avisos de pedido y los enlaces de descarga de los productos digitales
 * (N11): conviene saber que es real antes de dejar encargar.
 */
class EmailVerificationController extends Controller
{
    /**
     * Destino del enlace firmado que llega por correo. Verifica y devuelve al
     * usuario a la SPA, que es donde continua el flujo.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail() && $request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->away(
            rtrim((string) config('app.frontend_url'), '/').'/perfil?verificado=1'
        );
    }

    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'La direccion ya estaba verificada.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Correo de verificacion enviado.']);
    }
}
