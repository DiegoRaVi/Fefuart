<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
     *
     * **El usuario sale de la ruta, no de la sesion.** Aqui habia un
     * `EmailVerificationRequest`, y era un fallo: su `authorize()` hace
     * `$this->user()->getKey()`, asi que exige sesion iniciada. Pero esta
     * ruta se declaro **sin `auth`** justamente porque quien pincha lo hace
     * desde su gestor de correo, que puede abrir el enlace en otro navegador
     * o sin cookie. Las dos cosas juntas daban un 500 en el caso normal.
     *
     * Lo que autoriza es la **firma** de la URL, que valida el middleware
     * `signed` antes de llegar aqui: la firma cubre `id` y `hash` y caduca,
     * asi que no se puede fabricar ni reutilizar indefinidamente. El `hash`
     * se comprueba ademas contra el correo actual, para que un enlace emitido
     * antes de un cambio de direccion deje de valer.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        abort_unless(
            hash_equals(sha1($user->getEmailForVerification()), $hash),
            Response::HTTP_FORBIDDEN,
        );

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
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
