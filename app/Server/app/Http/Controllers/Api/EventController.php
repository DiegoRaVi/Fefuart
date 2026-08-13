<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

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

    public function cancel(Event $event): EventResource
    {
        $this->authorize('cancel', $event);

        if (! $event->status->canTransitionTo(EventStatus::Cancelled)) {
            throw ValidationException::withMessages([
                'status' => 'Este evento ya no se puede cancelar.',
            ]);
        }

        $event->status = EventStatus::Cancelled;
        $event->save();

        return EventResource::make($event);
    }
}
