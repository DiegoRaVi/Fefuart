<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SEC-010 — fijate en lo que no hay: `status`.
 *
 * En v1 `EventController::updateEvent` hacia
 * `$event->update($request->only([… 'status']))`, de modo que el propietario
 * podia pasar su evento a `confirmed` y colarse en la agenda. Aqui el estado
 * no se valida porque no se acepta: lo fija el servidor.
 */
class StoreEventRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:30'],

            // N17 — dos clientes pueden pedir la misma fecha; solo uno llega
            // a confirmarse. Por eso no hay regla de unicidad aqui.
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'schedule' => ['required', Rule::in(['morning', 'evening'])],
            'location' => ['required', 'string', 'max:255'],

            // N14 — lo que la artista necesita para presupuestar y que v1 no
            // pedia. Opcionales porque quien no lo sepa todavia no deberia
            // quedarse sin poder preguntar.
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'duration_hours' => ['nullable', 'integer', 'min:1', 'max:24'],
            'event_type' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'titulo',
            'event_date' => 'fecha del evento',
            'schedule' => 'franja',
            'location' => 'lugar',
            'guest_count' => 'numero de invitados',
            'duration_hours' => 'duracion',
            'event_type' => 'tipo de evento',
        ];
    }
}
