<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Las reglas de composicion del carrito: que se puede anadir, con que
 * entrega y en que cantidad. El cuanto cuesta lo decide PricingService.
 *
 * Todo lo que hay aqui vivia en v1 dentro de un `<script>` de cada
 * formulario, o directamente no existia.
 */
class CartService
{
    public function __construct(private readonly PricingService $pricing) {}

    /**
     * DB-006 — v1 no impedia varias ordenes en estado `cart` por usuario, y
     * `getCartOrder` resolvia el empate con un `->first()`. `firstOrCreate`
     * lo evita en aplicacion; la garantia a nivel de base de datos necesita
     * el mismo indice sobre columna generada que N16 y va con el (Fase 5).
     */
    /**
     * Lo que se enseña al consultar el carrito. **No crea nada**: un GET no
     * puede tener efectos, y con creacion perezosa cualquier prefetch del
     * navegador dejaba una fila por visita. Quien no tiene carrito ve uno
     * vacio, y la primera linea es la que lo crea de verdad.
     */
    public function currentCartFor(User $user): Order
    {
        $cart = $this->existingCartFor($user);

        if ($cart !== null) {
            return $cart;
        }

        $vacio = new Order;
        $vacio->status = OrderStatus::Cart;
        $vacio->subtotal = '0.00';
        $vacio->shipping_total = '0.00';
        $vacio->total = '0.00';
        $vacio->setRelation('items', collect());

        return $vacio;
    }

    public function cartFor(User $user): Order
    {
        $cart = $this->existingCartFor($user);

        if ($cart === null) {
            // Se asigna campo a campo y no con `firstOrCreate`: ni `user_id`
            // ni `status` son fillable, justamente para que no puedan llegar
            // del cuerpo de una peticion (SEC-003).
            $cart = new Order;
            $cart->user_id = $user->id;
            $cart->status = OrderStatus::Cart;
            $cart->subtotal = '0.00';
            $cart->shipping_total = '0.00';
            $cart->total = '0.00';
            $cart->save();
        }

        return $cart->load($this->itemRelations());
    }

    private function existingCartFor(User $user): ?Order
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where('status', OrderStatus::Cart)
            ->with($this->itemRelations())
            ->first();
    }

    public function addItem(
        User $user,
        Product $product,
        ProductVariant $variant,
        ShippingMethod $shippingMethod,
        int $quantity,
        ?string $customerNotes = null,
        ?MediaAsset $referenceMedia = null,
    ): Order {
        $this->guardProduct($product);
        $this->guardVariant($product, $variant);
        $this->guardDelivery($variant, $shippingMethod);
        $this->guardQuantity($product, $shippingMethod, $quantity);
        $this->guardReferenceImage($product, $referenceMedia);

        // SEC-006: los tres importes salen del catalogo. Nada de lo que venga
        // en el cuerpo de la peticion llega hasta aqui.
        $price = $this->pricing->priceLine($variant, $quantity);

        return DB::transaction(function () use (
            $user, $product, $variant, $shippingMethod, $quantity, $customerNotes, $referenceMedia, $price
        ) {
            $cart = $this->cartFor($user);

            $item = new OrderItem([
                'quantity' => $quantity,
                'customer_notes' => $customerNotes,
            ]);

            $item->order_id = $cart->id;
            $item->product_id = $product->id;
            $item->product_variant_id = $variant->id;
            $item->delivery_type = $shippingMethod->code;
            $item->reference_media_id = $referenceMedia?->id;

            // El snapshot congela el catalogo tal y como estaba al comprar.
            $item->product_name = $product->name;
            $item->variant_name = $variant->name;
            $item->unit_price = $price->unitPrice;
            $item->additional_copy_price = $price->additionalCopyPrice;
            $item->line_total = $price->lineTotal;

            $item->save();

            return $this->recalculate($cart);
        });
    }

    public function updateQuantity(OrderItem $item, int $quantity): Order
    {
        $item->loadMissing(['product', 'variant', 'order']);

        $shippingMethod = ShippingMethod::query()->where('code', $item->delivery_type)->sole();

        $this->guardQuantity($item->product, $shippingMethod, $quantity);

        // Se vuelve a calcular sobre la variante actual del catalogo, no
        // sobre el snapshot: mientras la linea siga en el carrito, el precio
        // que se aplica es el vigente.
        $price = $this->pricing->priceLine($item->variant, $quantity);

        return DB::transaction(function () use ($item, $quantity, $price) {
            $item->quantity = $quantity;
            $item->unit_price = $price->unitPrice;
            $item->additional_copy_price = $price->additionalCopyPrice;
            $item->line_total = $price->lineTotal;
            $item->save();

            return $this->recalculate($item->order);
        });
    }

    public function removeItem(OrderItem $item): Order
    {
        return DB::transaction(function () use ($item) {
            $order = $item->order;
            $item->delete();

            return $this->recalculate($order);
        });
    }

    /**
     * SEC-006 — los importes del pedido se recalculan enteros en cada cambio.
     * En v1 el carrito hacia un `PATCH` por linea, cada uno con un total
     * parcial (BUG-005), asi que el total final dependia de cual llegara el
     * ultimo.
     */
    private function recalculate(Order $order): Order
    {
        $order->load($this->itemRelations());

        $totals = $this->pricing->totalsFor($order);

        $order->subtotal = $totals->subtotal;
        $order->shipping_total = $totals->shippingTotal;
        $order->total = $totals->total;
        $order->shipping_method_id = $totals->shippingMethodId;
        $order->save();

        return $order->load($this->itemRelations());
    }

    private function guardProduct(Product $product): void
    {
        if (! $product->is_active) {
            throw ValidationException::withMessages([
                'product_id' => 'Ese producto ya no esta disponible.',
            ]);
        }
    }

    private function guardVariant(Product $product, ProductVariant $variant): void
    {
        if ($variant->product_id !== $product->id || ! $variant->is_active) {
            throw ValidationException::withMessages([
                'variant_id' => 'Esa opcion no pertenece a este producto.',
            ]);
        }
    }

    /**
     * N7 — el tipo de entrega lo limita la variante. En v1 era un `if` en el
     * navegador, asi que el servidor aceptaba cualquier combinacion.
     */
    private function guardDelivery(ProductVariant $variant, ShippingMethod $shippingMethod): void
    {
        $admitida = $variant->shippingMethods()
            ->whereKey($shippingMethod->id)
            ->exists();

        if (! $admitida) {
            throw ValidationException::withMessages([
                'shipping_method_id' => 'Esa opcion no admite ese tipo de entrega.',
            ]);
        }
    }

    /**
     * N3 y D26 — la cantidad son copias de la misma lamina. En entrega
     * digital es el mismo fichero, asi que no hay copias que cobrar.
     */
    private function guardQuantity(Product $product, ShippingMethod $shippingMethod, int $quantity): void
    {
        if (! $shippingMethod->code->allowsMultipleCopies() && $quantity > 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Una entrega digital es un unico archivo.',
            ]);
        }

        if ($quantity > $product->max_quantity) {
            throw ValidationException::withMessages([
                'quantity' => "No se pueden encargar mas de {$product->max_quantity} copias.",
            ]);
        }
    }

    /**
     * N9 — la foto no es un adjunto opcional: es el material de partida. La
     * propiedad la comprueba la Policy antes de llegar aqui.
     */
    private function guardReferenceImage(Product $product, ?MediaAsset $referenceMedia): void
    {
        if ($product->requires_reference_image && $referenceMedia === null) {
            throw ValidationException::withMessages([
                'reference_media_id' => 'Este encargo se dibuja a partir de tu foto: hace falta subirla.',
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function itemRelations(): array
    {
        // PERF-001 — el carrito de v1 pedia los datos de cada linea por
        // separado al renderizar.
        return ['items.referenceMedia', 'items.variant', 'shippingMethod'];
    }
}
