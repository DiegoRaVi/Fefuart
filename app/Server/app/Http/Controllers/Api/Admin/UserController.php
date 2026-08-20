<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * D22 — la artista atiende una peticion de supresion.
 *
 * Existe porque las peticiones del art. 17 llegan por correo o por telefono
 * la mitad de las veces, y alguien tiene que poder atenderlas sin pedirle al
 * cliente que entre y lo haga el mismo.
 */
class UserController extends Controller
{
    /**
     * @throws ValidationException se intenta suprimir a si misma
     */
    public function destroy(Request $request, User $user, AccountDeletionService $cuentas): Response
    {
        // Dejaria la tienda sin nadie que la atienda, y N20 dice que la
        // promocion a admin nunca ocurre por peticion HTTP: no habria vuelta.
        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'user' => 'No puedes suprimir tu propia cuenta de administradora.',
            ]);
        }

        $cuentas->suprimir($user);

        return response()->noContent();
    }
}
