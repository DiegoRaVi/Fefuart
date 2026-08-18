<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;

/**
 * D10 — el centro de avisos dentro de la aplicacion.
 *
 * Todo se resuelve **a traves de la relacion del usuario en sesion**
 * (`$request->user()->notifications()`), nunca buscando en toda la tabla
 * para comparar despues de quien es el aviso. Esa segunda forma es la que
 * produjo SEC-003, SEC-004, SEC-008 y SEC-009 en v1: funciona hasta el sitio
 * donde alguien se olvida de comprobar.
 *
 * Por eso no hay Policy: no queda ninguna comparacion en linea que
 * sustituir. Una Policy aqui protegeria un `where user_id` que ya esta en la
 * consulta.
 */
class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $usuario = $request->user();

        $avisos = $usuario->notifications()
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return NotificationResource::collection($avisos)->additional([
            // Lo que pinta el contador de la cabecera. Viaja con la lista
            // para no obligar a una segunda peticion solo por un numero.
            'meta' => ['no_leidos' => $usuario->unreadNotifications()->count()],
        ]);
    }

    /**
     * El IDOR de este endpoint.
     *
     * `findOrFail` sobre la relacion devuelve **404 y no 403** cuando el
     * aviso es de otra persona, y esa es la respuesta correcta: que exista no
     * es asunto de quien pregunta, y un 403 se lo estaria confirmando.
     */
    public function read(Request $request, string $notification): NotificationResource
    {
        /** @var DatabaseNotification $aviso */
        $aviso = $request->user()->notifications()->findOrFail($notification);

        // `markAsRead()` ya respeta la primera lectura: si `read_at` tiene
        // valor no lo reescribe. Marcar dos veces no mueve la fecha.
        $aviso->markAsRead();

        return NotificationResource::make($aviso);
    }
}
