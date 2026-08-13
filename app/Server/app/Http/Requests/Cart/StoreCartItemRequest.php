<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SEC-006 — fijate en lo que **no** hay aqui: ningun campo de precio.
 *
 * En v1 el equivalente era `'price' => 'required|numeric'`, de modo que el
 * navegador decidia cuanto costaba el encargo. Aqui el cuerpo solo dice
 * *que* se encarga; el cuanto lo resuelve PricingService contra el catalogo.
 * Un `price` en la peticion no llega a leerse nunca.
 */
class StoreCartItemRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
            'reference_media_id' => ['nullable', 'integer', 'exists:media_assets,id'],
        ];
    }
}
