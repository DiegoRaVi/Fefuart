<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CampoDeBusqueda;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexEventsRequest;
use App\Http\Requests\Admin\QuoteEventRequest;
use App\Http\Requests\Admin\UpdateEventStatusRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\QuoteService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * El backoffice de eventos.
 *
 * Presupuestar tiene endpoint propio y no pasa por `/status`: emitir un
 * presupuesto no es cambiar un estado, es fijar un importe, calcular la
 * señal y arrancar un plazo de caducidad. Confirmar no esta aqui en
 * absoluto — lo hace el webhook cuando la señal se cobra (N15).
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
            ->when(
                isset($filtros['buscar_por']),
                function ($query) use ($filtros) {
                    $campo = CampoDeBusqueda::from($filtros['buscar_por']);

                    $query->buscar(
                        $filtros['q'],
                        columnas: $campo->columnasDeEvento(),
                        relaciones: $campo->relacionesDeEvento(),
                        porId: false,
                    );
                },
                fn ($query) => $query->buscar(
                    $filtros['q'] ?? null,
                    columnas: ['title', 'location', 'phone'],
                    relaciones: ['user' => ['name', 'email']],
                    porId: false,
                ),
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
     * D6, N13 — la artista emite el presupuesto.
     *
     * Del cuerpo solo llega el importe total. La señal la calcula
     * PricingService con el porcentaje configurado (N15) y se guarda junto
     * al presupuesto: si manana cambia el porcentaje, este evento sigue
     * teniendo la señal que se le dijo al cliente.
     */
    public function quote(QuoteEventRequest $request, Event $event, QuoteService $presupuestos): EventResource
    {
        $this->authorize('quote', $event);

        $datos = $request->validated();

        $presupuestos->presupuestar(
            $event,
            (string) $datos['quoted_amount'],
            $datos['validez_dias'] ?? null,
        );

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
