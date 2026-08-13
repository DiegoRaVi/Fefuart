<?php

namespace App\Http\Requests\Admin;

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

            // Una sola caja: numero de pedido, nombre y email de la cuenta,
            // nombre del envio o telefono. Lo que Felicitas tenga delante.
            'q' => ['sometimes', 'string', 'max:255'],

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
        ];
    }
}
