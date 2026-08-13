<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexEventsRequest;
use App\Http\Requests\Admin\UpdateEventStatusRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * El backoffice de eventos.
 *
 * De momento solo llega hasta rechazar, cancelar y completar. Presupuestar y
 * confirmar necesitan importe y señal, y esas columnas son de la Fase 5
 * (D27): tendran su propio endpoint cuando haya con que rellenarlas.
 */
class EventController extends Controller
{
    /**
     * PERF-004 — v1 devolvia todos los eventos sin paginar, con el usuario
     * eager-loaded de cada uno.
     */
    public function index(IndexEventsRequest $request): AnonymousResourceCollection
    {
        $filtros = $request->validated();

        $events = Event::query()
            ->with('user')
            ->when(
                isset($filtros['status']),
                fn ($query) => $query->where('status', $filtros['status']),
            )
            // Un evento se recuerda por el titulo o por donde era, ademas de
            // por quien lo pidio. El id no entra: nadie llama diciendo «el
            // evento numero siete».
            ->buscar(
                $filtros['q'] ?? null,
                columnas: ['title', 'location', 'phone'],
                relaciones: ['user' => ['name', 'email']],
                porId: false,
            )
            ->entreFechas('event_date', $filtros['desde'] ?? null, $filtros['hasta'] ?? null)
            ->orderBy('event_date')
            ->paginate(20)
            ->withQueryString();

        return EventResource::collection($events);
    }

    public function show(Event $event): EventResource
    {
        return EventResource::make($event->load('user'));
    }

    /**
     * SEC-010 al reves: aqui si se puede mover el estado, porque quien
     * pregunta ha pasado por `admin`. Lo que sigue sin poder saltarse es la
     * maquina de estados.
     */
    public function updateStatus(UpdateEventStatusRequest $request, Event $event): EventResource
    {
        $destino = EventStatus::from($request->validated()['status']);

        if (! $event->status->canTransitionTo($destino)) {
            throw ValidationException::withMessages([
                'status' => "Un evento en «{$event->status->value}» no puede pasar a «{$destino->value}».",
            ]);
        }

        $event->status = $destino;
        $event->save();

        return EventResource::make($event->load('user'));
    }
}
