<?php

namespace App\Http\Resources;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ARCH-005 — el contrato se declara aqui, no lo dicta el esquema.
 *
 * El presupuesto y la señal salen siempre que existan: son lo que el cliente
 * necesita para decidir si acepta.
 *
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'phone' => $this->phone,
            'event_date' => $this->event_date->toDateString(),
            'schedule' => $this->schedule,
            'location' => $this->location,
            'guest_count' => $this->guest_count,
            'duration_hours' => $this->duration_hours,
            'event_type' => $this->event_type,
            'status' => $this->status->value,

            // D6, N15 — nulos mientras la artista no haya presupuestado.
            'quoted_amount' => $this->quoted_amount,
            'deposit_amount' => $this->deposit_amount,
            'quote_expires_at' => $this->quote_expires_at?->toAtomString(),
            'quote_expired' => $this->presupuestoCaducado(),

            /*
             * N21 — si la señal esta cobrada, cancelar tiene consecuencias
             * distintas segun quien cancele, y las pantallas tienen que
             * poder decirlo antes de que nadie pulse.
             *
             * Se deduce del estado y no de una consulta a `payments`: a
             * `confirmed` solo se llega con la señal cobrada, asi que
             * preguntarlo por fila seria el N+1 de PERF-001 otra vez.
             */
            'deposit_paid' => in_array(
                $this->status,
                [EventStatus::Confirmed, EventStatus::Completed],
                strict: true
            ),
            // Que puede hacer quien esta mirando, resuelto en servidor: el
            // cliente no tiene que deducirlo del estado por su cuenta.
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'cancel' => $request->user()?->can('cancel', $this->resource) ?? false,
                'accept_quote' => $request->user()?->can('acceptQuote', $this->resource) ?? false,
                'quote' => $request->user()?->can('quote', $this->resource) ?? false,
            ],
            'created_at' => $this->created_at?->toAtomString(),

            // SEC-009 — mismo criterio que en OrderResource: administradora
            // **y** relacion cargada a proposito.
            'customer' => $this->when(
                $request->user()?->isAdmin() === true && $this->relationLoaded('user'),
                fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ],
            ),
        ];
    }
}
