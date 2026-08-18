<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D6, N13 — el presupuesto que emite la artista.
 *
 * Lo unico que llega es el importe total. La señal **no** se acepta del
 * cuerpo: la calcula PricingService a partir del porcentaje configurado
 * (N15), igual que ningun precio del catalogo llega del cliente (SEC-006).
 * Que aqui quien escribe sea la administradora no cambia la regla; cambia
 * quien puede equivocarse.
 */
class QuoteEventRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Hasta 99.999,99 EUR y con dos decimales como mucho: es lo que
            // cabe en `decimal(10,2)`, y pasarse ahi seria un error de la
            // base de datos y no un mensaje que el usuario pueda entender.
            'quoted_amount' => ['required', 'numeric', 'min:1', 'max:99999.99', 'decimal:0,2'],

            // Opcional: si no viene, vale `settings.quote_validity_days`.
            'validez_dias' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'quoted_amount' => 'importe del presupuesto',
            'validez_dias' => 'dias de validez',
        ];
    }
}
