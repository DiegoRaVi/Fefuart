<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * BUG-002 — la ruta que en v1 apuntaba a un metodo inexistente y respondia
 * 500, de modo que el usuario no podia corregir su propia solicitud.
 *
 * Tampoco acepta `status`, que es lo que convertia ese arreglo en SEC-010.
 */
class UpdateEventRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'event_date' => ['sometimes', 'date', 'after_or_equal:today'],
            'schedule' => ['sometimes', Rule::in(['morning', 'evening'])],
            'location' => ['sometimes', 'string', 'max:255'],
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
