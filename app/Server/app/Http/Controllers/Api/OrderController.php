<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Los pedidos del cliente.
 *
 * No hay ningun endpoint que acepte un estado o un total en el cuerpo. En v1
 * «Pagar» era un `PATCH /orders/{id} {status:'pending'}` y el mismo endpoint
 * admitia `total` y `address`, sin comprobar propiedad ni rol (SEC-003). Las
 * transiciones se piden por sub-recurso y las decide el servidor.
 */
class OrderController extends Controller
{
    /**
     * BUG-003 — v1 tenia `GET /user-orders` apuntando a un metodo que
     * esperaba un `{id}` que la ruta no definia. El pedido de cada cual sale
     * de la sesion, no de la URL.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // Mismo criterio que en el backoffice: el listado enseña cuantos
        // encargos hay y el total, no los encargos.
        $orders = $request->user()->orders()
            ->placed()
            ->withCount('items')
            ->with('shippingMethod')
            ->orderByDesc('placed_at')
            ->paginate(15);

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        // SEC-003/SEC-008: por Policy. En v1 la comprobacion estaba
        // comentada, y la que existia comparaba un rol con un id (BUG-004).
        $this->authorize('view', $order);

        return OrderResource::make($order->load(['items.referenceMedia', 'shippingMethod']));
    }

    /**
     * N12 — el cliente cancela solo antes de pagar; despues lo hace la
     * artista desde el backoffice. Quien puede, lo decide la Policy; desde
     * donde se puede, el enum.
     */
    public function cancel(Order $order, OrderService $pedidos): OrderResource
    {
        $this->authorize('cancel', $order);

        // La transicion la valida OrderService, no este metodo (§4). Sin
        // aviso: el correo le contaria al cliente lo que acaba de pulsar.
        $pedidos->cambiarEstado($order, OrderStatus::Cancelled, avisar: false);

        return OrderResource::make($order->load(['items.referenceMedia', 'shippingMethod']));
    }
}
