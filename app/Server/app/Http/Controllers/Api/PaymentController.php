<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\PagoEnCursoException;
use App\Services\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Stripe\Exception\ApiErrorException;

/**
 * Abrir la sesion de pago de un pedido.
 *
 * El cuerpo va vacio a proposito: aqui no se manda ni importe ni moneda ni
 * metodo. Todo sale del pedido, que a su vez lo calculo PricingService
 * (SEC-006). En v1 «Pagar» era un `PATCH /orders/{id}` que aceptaba `total`
 * y `status` del navegador.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly StripePaymentService $pagos) {}

    public function store(Order $order): JsonResponse
    {
        $this->authorize('pay', $order);

        try {
            $sesion = $this->pagos->cobrarPedido($order);
        } catch (PagoEnCursoException) {
            // El navegador volvio de Stripe antes que el webhook. No es un
            // error del cliente: es una carrera, y lo unico que toca es
            // esperar. Abrir otra sesion seria cobrar dos veces.
            return response()->json([
                'message' => 'Ya hemos recibido tu pago. Lo estamos confirmando; en unos segundos veras el pedido como pagado.',
            ], 409);
        } catch (ApiErrorException $e) {
            // SEC-012 — el detalle va al log, no al cliente.
            report($e);

            return response()->json([
                'message' => 'No hemos podido abrir la pasarela de pago. Intentalo de nuevo en unos minutos.',
            ], 502);
        }

        return response()->json([
            'url' => $sesion->url,
            'payment_id' => $sesion->payment->id,
        ]);
    }
}
