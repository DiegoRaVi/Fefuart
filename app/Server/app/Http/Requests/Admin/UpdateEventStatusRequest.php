<?php

namespace App\Http\Requests\Admin;

use App\Enums\EventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solo las transiciones que no necesitan datos de mas.
 *
 * D27 — presupuestar exige importe y confirmar exige señal cobrada, y esas
 * columnas llegan en la Fase 5. Un evento en `quoted` sin importe no es un
 * presupuesto: es un estado a medias que ademas dejaria al cliente sin poder
 * aceptar nada. `quoted` y `confirmed` tendran sus propios endpoints
 * (`/quote` y el webhook de la señal) cuando haya con que rellenarlos.
 */
class UpdateEventStatusRequest extends FormRequest
{
    private const SIN_DATOS_EXTRA = [
        EventStatus::Rejected,
        EventStatus::Cancelled,
        EventStatus::Completed,
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::enum(EventStatus::class)->only(self::SIN_DATOS_EXTRA),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.Illuminate\Validation\Rules\Enum' => 'Ese estado no se puede fijar desde aqui.',
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
