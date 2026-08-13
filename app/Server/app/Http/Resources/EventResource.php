<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ARCH-005 — el contrato se declara aqui, no lo dicta el esquema.
 *
 * El presupuesto y la señal no salen todavia porque las columnas no existen:
 * llegan en la Fase 5 (D27).
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
            // Que puede hacer quien esta mirando, resuelto en servidor: el
            // cliente no tiene que deducirlo del estado por su cuenta.
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'cancel' => $request->user()?->can('cancel', $this->resource) ?? false,
            ],
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
