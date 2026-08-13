<?php

namespace App\Http\Requests\Admin;

use App\Enums\CampoDeBusqueda;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Los filtros del listado tambien se validan. Un estado inventado en la query
 * string devolviendo la lista entera es la clase de silencio que hace que
 * nadie se entere de que el filtro no funciona.
 */
class IndexOrdersRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],

            // Sin `buscar_por`, la caja rapida: mira en numero, nombre y
            // email de la cuenta, nombre del envio y telefono a la vez.
            // Con el, la busqueda precisa del modal, acotada a ese campo.
            // `nullable` y no `sometimes`: con `sometimes`, un `q` ausente
            // salta todas las reglas del campo, `required_with` incluida, y
            // el controller acababa leyendo un indice que no existe.
            'q' => ['nullable', 'required_with:buscar_por', 'string', 'max:255'],
            'buscar_por' => [
                'sometimes',
                Rule::enum(CampoDeBusqueda::class)->only(CampoDeBusqueda::dePedidos()),
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
