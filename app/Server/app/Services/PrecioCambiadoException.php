<?php

namespace App\Services;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los importes del carrito han cambiado entre que el cliente los vio y el
 * momento de encargar, porque Felicitas edito el catalogo desde el
 * backoffice (D5).
 *
 * Es un 409 y no un 422: los datos que mando el cliente eran validos, lo que
 * ha cambiado es el estado del servidor. La respuesta lleva el pedido con
 * los importes nuevos para que la pantalla pueda ensenar la diferencia en
 * vez de un error a secas.
 */
class PrecioCambiadoException extends Exception
{
    public function __construct(private readonly Order $order)
    {
        parent::__construct('Los precios han cambiado desde que anadiste estos encargos.');
    }

    public function render(Request $request): JsonResponse
    {
        return OrderResource::make($this->order)
            ->additional(['message' => $this->getMessage()])
            ->response()
            ->setStatusCode(Response::HTTP_CONFLICT);
    }
}
