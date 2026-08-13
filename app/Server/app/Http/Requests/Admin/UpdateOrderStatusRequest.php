<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SEC-006 — solo `status`. Ningun importe entra por aqui, tampoco viniendo
 * de la administradora: los totales los calcula PricingService y no se
 * corrigen a mano desde una peticion.
 *
 * Que el valor exista en el enum es lo unico que se valida aqui; que la
 * transicion sea posible lo decide OrderStatus en el controller.
 */
class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['status' => 'estado'];
    }
}
