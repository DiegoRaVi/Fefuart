<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\User;
use App\Notifications\NewLiveArtRequest;
use App\Services\Payments\PagoEnCursoException;
use App\Services\QuoteService;
use App\Services\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Stripe\Exception\ApiErrorException;

/**
 * Solicitudes de Live Art. N13: el precio siempre es a medida, asi que lo
 * unico que hace el cliente es pedir; presupuestar y confirmar son de la
 * administradora y viven en el backoffice.
 *
 * Ninguna ruta de aqui acepta `status`. En v1 `updateEvent` hacia
 * `$event->update($request->only([… 'status']))` (SEC-010), y ademas ese
 * metodo no existia, de modo que la ruta respondia 500 (BUG-002). Arreglar
 * lo segundo sin lo primero habria activado el hallazgo.
 */
class EventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $events = $request->user()->events()
            ->orderByDesc('event_date')
            ->paginate(15);

        return EventResource::collection($events);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = new Event($request->validated());

        // Fijados en servidor, nunca desde el cuerpo.
        $event->user_id = $request->user()->id;
        $event->status = EventStatus::Requested;
        $event->save();

        // D10 — N13 dice que el precio es siempre a medida, asi que este
        // flujo se atasca hasta que la artista presupuesta. Si no se entera
        // de que ha entrado una solicitud, el cliente espera indefinidamente.
        Notification::send(User::admins()->get(), new NewLiveArtRequest($event));

        return EventResource::make($event)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Event $event): EventResource
    {
        $this->authorize('view', $event);

        return EventResource::make($event);
    }

    /**
     * BUG-002. La Policy limita ademas la edicion a las solicitudes que
     * todavia no se han presupuestado: cambiar la fecha despues de recibir un
     * presupuesto es pedir otro presupuesto.
     */
    public function update(UpdateEventRequest $request, Event $event): EventResource
    {
        $this->authorize('update', $event);

        $event->update($request->validated());

        return EventResource::make($event);
    }

    /**
     * D6, N15 — el cliente acepta el presupuesto y pasa a pagar la señal.
     *
     * Los dos pasos van juntos porque para el cliente son uno solo: aceptar
     * es reservar, y reservar es pagar. Aceptar deja el evento en `accepted`;
     * quien lo confirma es el webhook cuando la señal se cobra de verdad.
     *
     * Se puede repetir. Si abandona la pagina de Stripe, el evento se queda
     * en `accepted` y volver aqui devuelve la sesion que sigue abierta.
     */
    public function acceptQuote(
        Event $event,
        QuoteService $presupuestos,
        StripePaymentService $pagos,
    ): JsonResponse {
        $this->authorize('acceptQuote', $event);

        $presupuestos->aceptar($event);

        try {
            $sesion = $pagos->cobrarSenal($event);
        } catch (PagoEnCursoException) {
            return response()->json([
                'message' => 'Ya hemos recibido tu señal. La estamos confirmando; en unos segundos veras la fecha reservada.',
            ], 409);
        } catch (ApiErrorException $e) {
            // SEC-012 — el detalle va al log, no al cliente.
            report($e);

            return response()->json([
                'message' => 'No hemos podido abrir la pasarela de pago. Intentalo de nuevo en unos minutos.',
            ], 502);
        }

        return response()->json([
            'url' => $sesion->url,
            'payment_id' => $sesion->payment->id,
            'event' => EventResource::make($event->fresh()),
        ]);
    }

    /**
     * N21 — el cliente cancela, y si ya habia pagado la señal, la pierde: la
     * fecha estuvo bloqueada por el. Quien decide eso es QuoteService, que es
     * tambien quien sabe cuando si toca devolver.
     */
    public function cancel(Request $request, Event $event, QuoteService $presupuestos): EventResource
    {
        $this->authorize('cancel', $event);

        $presupuestos->cancelar($event, $request->user());

        return EventResource::make($event);
    }
}
