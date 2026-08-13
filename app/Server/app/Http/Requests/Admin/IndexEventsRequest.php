<?php

namespace App\Http\Requests\Admin;

use App\Enums\CampoDeBusqueda;
use App\Enums\EventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexEventsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(EventStatus::class)],

            // Mismo criterio que en pedidos. Aqui la caja rapida mira ademas
            // titulo y lugar, que es como se recuerda un evento, y el modal
            // los ofrece por separado: «Toledo» puede ser el nombre de la
            // boda o donde se celebra, y no son lo mismo.
            // `nullable` y no `sometimes`: con `sometimes`, un `q` ausente
            // salta `required_with` y el controller lee un indice que no
            // existe.
            'q' => ['nullable', 'required_with:buscar_por', 'string', 'max:255'],
            'buscar_por' => [
                'sometimes',
                Rule::enum(CampoDeBusqueda::class)->only(CampoDeBusqueda::deEventos()),
            ],

            'desde' => ['sometimes', 'date'],
            'hasta' => ['sometimes', 'date', 'after_or_equal:desde'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => 'estado',
            'q' => 'busqueda',
            'buscar_por' => 'campo de busqueda',
            'desde' => 'fecha de inicio',
            'hasta' => 'fecha de fin',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hasta.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
            'q.required_with' => 'Escribe que quieres buscar.',
        ];
    }
}
