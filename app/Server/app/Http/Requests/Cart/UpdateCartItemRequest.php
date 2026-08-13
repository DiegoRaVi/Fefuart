<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Lo unico editable de una linea del carrito es la cantidad. Cambiar de
 * variante o de entrega es quitar la linea y volver a anadirla, porque el
 * precio y las reglas de N7 dependen de ambas.
 */
class UpdateCartItemRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
