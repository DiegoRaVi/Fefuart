<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\DeliveryType;
use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;

/**
 * Cuatro numeros para el backoffice, y solo cuatro.
 *
 * Estuvo aparcado desde la Fase 4 con una razon buena y que sigue siendo
 * cierta: **nadie le ha preguntado a Felicitas que mira**. Se construye lo
 * minimo que casi seguro sirve —cuanto ha entrado este mes y que hay
 * pendiente de hacer— y queda dicho que esto no es el panel definitivo: lo
 * que ella mire un lunes por la manana es lo que deberia estar aqui.
 *
 * Dos son de dinero y dos son de trabajo. Mas de cuatro numeros en una
 * pantalla dejan de leerse.
 */
class MetricsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $desde = now()->startOfMonth();

        return response()->json([
            'data' => [
                'pedidos_del_mes' => Order::query()
                    ->placed()
                    ->where('placed_at', '>=', $desde)
                    ->count(),

                /*
                 * Solo lo cobrado. Un pedido en `pending_payment` puede ser
                 * un carrito que nadie terminara, y contarlo como
                 * facturacion daria un numero peor que no dar ninguno.
                 *
                 * `cancelled` tampoco entra aunque llegara a pagarse: si se
                 * devolvio, no es ingreso; y si no, es un caso que se mira a
                 * mano.
                 */
                'ingresos_del_mes' => number_format(
                    (float) Order::query()
                        ->whereIn('status', [
                            OrderStatus::Paid,
                            OrderStatus::InProgress,
                            OrderStatus::Shipped,
                            OrderStatus::Completed,
                        ])
                        ->where('placed_at', '>=', $desde)
                        ->sum('total'),
                    2,
                    '.',
                    '',
                ),

                // N13 — lo que espera una respuesta suya y nadie mas puede
                // dar: sin presupuesto, el cliente no puede hacer nada.
                'eventos_por_presupuestar' => Event::query()
                    ->where('status', EventStatus::Requested)
                    ->count(),

                // D20, N11 — cobrado y todavia sin entregar.
                'entregas_pendientes' => OrderItem::query()
                    ->where('delivery_type', DeliveryType::Digital)
                    ->whereNull('delivered_media_id')
                    ->whereHas('order', fn ($query) => $query->whereIn('status', [
                        OrderStatus::Paid,
                        OrderStatus::InProgress,
                        OrderStatus::Shipped,
                    ]))
                    ->count(),
            ],
        ]);
    }
}
