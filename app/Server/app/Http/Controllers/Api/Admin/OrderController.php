<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CampoDeBusqueda;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexOrdersRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
            // El listado enseña el numero de lineas y el total, no las
            // lineas: cargarlas con su foto de referencia para veinte
            // pedidos por pagina es traer datos que despues se tiran.
            ->withCount('items')
            ->with(['user', 'shippingMethod'])
            ->when(
                isset($filtros['status']),
                fn ($query) => $query->where('status', $filtros['status']),
            )
            // Con `buscar_por`, la busqueda precisa del modal: solo ese
            // campo. Sin el, la caja rapida mira en todos.
            ->when(
                isset($filtros['buscar_por']),
                function ($query) use ($filtros) {
                    $campo = CampoDeBusqueda::from($filtros['buscar_por']);

                    $query->buscar(
                        $filtros['q'],
                        columnas: $campo->columnasDePedido(),
                        relaciones: $campo->relacionesDePedido(),
                        porId: $campo->buscaPorId(),
                    );
                },
                fn ($query) => $query->buscar(
                    $filtros['q'] ?? null,
                    columnas: ['shipping_name', 'shipping_phone'],
                    relaciones: ['user' => ['name', 'email']],
                ),
            )
            ->entreFechas('placed_at', $filtros['desde'] ?? null, $filtros['hasta'] ?? null)
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
     * SEC-003 — quien valida la transicion es `OrderService`, no este
     * metodo: §4 del roadmap dice que la maquina de estados se comprueba en
     * Services y nunca en el controller.
     */
    public function updateStatus(
        UpdateOrderStatusRequest $request,
        Order $order,
        OrderService $pedidos,
    ): OrderResource {
        $pedidos->cambiarEstado($order, OrderStatus::from($request->validated()['status']));

        return OrderResource::make(
            $order->load(['user', 'items.referenceMedia', 'shippingMethod'])
        );
    }
}
