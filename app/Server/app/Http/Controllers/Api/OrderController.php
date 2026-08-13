<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

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
        $orders = $request->user()->orders()
            ->placed()
            ->with(['items.referenceMedia', 'shippingMethod'])
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
    public function cancel(Order $order): OrderResource
    {
        $this->authorize('cancel', $order);

        if (! $order->status->canTransitionTo(OrderStatus::Cancelled)) {
            throw ValidationException::withMessages([
                'status' => 'Este pedido ya no se puede cancelar.',
            ]);
        }

        $order->status = OrderStatus::Cancelled;
        $order->save();

        return OrderResource::make($order->load(['items.referenceMedia', 'shippingMethod']));
    }
}
