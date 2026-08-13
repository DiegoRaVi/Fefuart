<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexOrdersRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * El backoffice de pedidos: lo que v1 hacia en `admin.html`, con propiedad.
 *
 * Todas las rutas van tras `auth:sanctum` + `admin`, asi que la comprobacion
 * de rol esta en el middleware y solo ahi (ARCH-002).
 */
class OrderController extends Controller
{
    /**
     * PERF-001 — `admin.js:92,276` hacia un `GET /user/{id}` por cada fila
     * que renderizaba. Aqui el cliente y las lineas van eager-loaded, y hay
     * un test que cuenta consultas para que no vuelva a colarse un N+1.
     *
     * PERF-004 — y va paginado: v1 devolvia todas las filas.
     */
    public function index(IndexOrdersRequest $request): AnonymousResourceCollection
    {
        $filtros = $request->validated();

        $orders = Order::query()
            ->placed()
            ->with(['user', 'items.referenceMedia', 'shippingMethod'])
            ->when(
                isset($filtros['status']),
                fn ($query) => $query->where('status', $filtros['status']),
            )
            // La busqueda por email de v1, que es como Felicitas localiza un
            // encargo cuando alguien escribe preguntando.
            ->when(
                isset($filtros['email']),
                fn ($query) => $query->whereHas(
                    'user',
                    fn ($u) => $u->where('email', 'like', '%'.$filtros['email'].'%'),
                ),
            )
            ->orderByDesc('placed_at')
            ->paginate(20)
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        return OrderResource::make(
            $order->load(['user', 'items.referenceMedia', 'items.variant', 'shippingMethod'])
        );
    }

    /**
     * SEC-003 — la transicion la valida el enum, tambien para la
     * administradora. En v1 cualquiera mandaba `{"status":"paid"}` a
     * `PATCH /orders/{id}` y el servidor lo aceptaba sin mirar de donde
     * venia el pedido.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        $destino = OrderStatus::from($request->validated()['status']);

        if (! $order->status->canTransitionTo($destino)) {
            throw ValidationException::withMessages([
                'status' => "Un pedido en «{$order->status->value}» no puede pasar a «{$destino->value}».",
            ]);
        }

        $order->status = $destino;
        $order->save();

        return OrderResource::make(
            $order->load(['user', 'items.referenceMedia', 'shippingMethod'])
        );
    }
}
