<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * D21 — una cuenta desactivada deja de operar **en el acto**.
 *
 * Sin esto, desactivar solo cerraria la sesion desde la que se pidio: la del
 * movil, o la del ordenador de la oficina, seguiria funcionando hasta que
 * caducase la cookie. Y lo mismo con una cuenta suprimida (D22), que queda
 * desactivada — alli ya no hay nadie detras a quien dejar operar.
 *
 * Va con el grupo de la API y no en cada ruta: olvidarlo en una sola
 * significaria dejar una puerta abierta, y cual seria dificil de ver.
 */
class EnsureIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        // Solo aplica a quien viene autenticado: las rutas publicas —el
        // catalogo, la galeria— no tienen usuario y siguen su camino.
        if ($request->user() !== null && $request->user()->deactivated_at !== null) {
            // 401 y no 403: la sesion ha dejado de ser valida, que es
            // exactamente lo que significa. La SPA ya sabe que hacer con un
            // 401 — llevar al login.
            throw new AuthenticationException('Esta cuenta esta desactivada.');
        }

        return $next($request);
    }
}
