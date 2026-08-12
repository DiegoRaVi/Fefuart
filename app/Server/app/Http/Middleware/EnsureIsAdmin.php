<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a administradores.
 *
 * Sustituye al `IsAdmin` de v1, que resolvia el usuario por su cuenta con
 * `auth('api')->user()` y devolvia 403 tambien cuando el problema real era
 * que no habia sesion. Aqui la autenticacion es responsabilidad de
 * `auth:sanctum`, que va delante: este middleware solo decide sobre el rol,
 * de modo que un invitado recibe 401 y un cliente autenticado 403.
 *
 * La comprobacion vive aqui y solo aqui — v1 la repetia ademas en linea en
 * una decena de metodos de controller que ya estaban tras el middleware
 * (ARCH-002).
 */
class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->isAdmin() === true,
            Response::HTTP_FORBIDDEN,
            'Esta accion requiere permisos de administrador.'
        );

        return $next($request);
    }
}
