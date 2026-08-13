<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\StoreCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\OrderResource;
use App\Models\MediaAsset;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * El carrito. Devuelve siempre el pedido entero recalculado, no solo la
 * linea tocada: en v1 el navegador sumaba por su cuenta y mandaba un `PATCH`
 * por cada linea con su total parcial (BUG-005), asi que el total dependia
 * de cual llegara el ultimo.
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function show(): OrderResource
    {
        return OrderResource::make($this->cart->currentCartFor(request()->user()));
    }

    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $referenceMedia = isset($data['reference_media_id'])
            ? MediaAsset::query()->findOrFail($data['reference_media_id'])
            : null;

        // SEC-004/SEC-008: la foto tiene que ser suya. Sin esto, un
        // `reference_media_id` ajeno mete la foto de otra persona en el
        // pedido propio.
        if ($referenceMedia !== null) {
            $this->authorize('attach', $referenceMedia);
        }

        $order = $this->cart->addItem(
            user: $user,
            product: Product::query()->findOrFail($data['product_id']),
            variant: ProductVariant::query()->findOrFail($data['variant_id']),
            shippingMethod: ShippingMethod::query()->findOrFail($data['shipping_method_id']),
            quantity: $data['quantity'],
            customerNotes: $data['customer_notes'] ?? null,
            referenceMedia: $referenceMedia,
        );

        return OrderResource::make($order)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateCartItemRequest $request, OrderItem $item): OrderResource
    {
        $this->authorizeCartItem($item);

        return OrderResource::make(
            $this->cart->updateQuantity($item, $request->validated()['quantity'])
        );
    }

    public function destroy(OrderItem $item): OrderResource
    {
        $this->authorizeCartItem($item);

        return OrderResource::make($this->cart->removeItem($item));
    }

    /**
     * SEC-003/SEC-004 — la linea se autoriza a traves de su pedido, que es
     * quien tiene duenno. En v1 `DELETE /products/{id}` no comprobaba nada y
     * borraba la linea de cualquiera.
     */
    private function authorizeCartItem(OrderItem $item): void
    {
        $this->authorize('updateCart', $item->order);
    }
}
