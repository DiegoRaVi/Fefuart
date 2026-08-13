<?php

namespace App\Services;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Pricing\OrderTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Convierte el carrito en pedido.
 *
 * Aqui no hay pago. La Fase 5 **inserta** el cobro entre `pending_payment` y
 * `paid`; nada de lo que hay en este servicio cambia por eso. En v1 «Pagar»
 * era un `PATCH /orders/{id} {status:'pending'}` que ademas admitia `total`
 * y `address` (SEC-003, SEC-006).
 */
class CheckoutService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly CartService $cart,
    ) {}

    /**
     * @param  array<string, string|null>  $direccion
     *
     * @throws ValidationException carrito vacio, catalogo caido o falta la direccion
     * @throws PrecioCambiadoException los importes han cambiado bajo los pies del cliente
     */
    public function checkout(User $user, array $direccion): Order
    {
        $order = $this->cart->cartFor($user);

        if ($order->items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Tu carrito esta vacio.',
            ]);
        }

        $this->guardCatalogoVigente($order);
        $this->guardDireccion($order, $direccion);

        // Se vuelve a calcular contra el catalogo de ahora mismo, no contra
        // lo que se guardo al anadir la linea: entre medias Felicitas puede
        // haber cambiado un precio desde el backoffice (D5).
        $totales = $this->reprice($order);

        if ($this->hanCambiado($order, $totales)) {
            // Ni cobrar el nuevo en silencio ni cobrar el viejo: se guarda lo
            // que toca, se devuelve al cliente y que confirme otra vez.
            $this->persistirTotales($order, $totales);

            throw new PrecioCambiadoException($order->fresh(['items.referenceMedia', 'shippingMethod']));
        }

        return DB::transaction(function () use ($order, $direccion, $totales) {
            $this->persistirTotales($order, $totales);

            $order->fill(array_filter($direccion, fn ($v) => $v !== null));

            if (! $order->status->canTransitionTo(OrderStatus::PendingPayment)) {
                throw new RuntimeException("No se puede encargar un pedido en estado {$order->status->value}.");
            }

            $order->status = OrderStatus::PendingPayment;
            $order->placed_at = now();
            $order->save();

            return $order->load(['items.referenceMedia', 'shippingMethod']);
        });
    }

    /**
     * Un producto retirado o una variante desactivada mientras el carrito
     * estaba abierto. Es raro, pero cobrar algo que ya no se vende lo es mas.
     */
    private function guardCatalogoVigente(Order $order): void
    {
        $order->loadMissing(['items.product', 'items.variant']);

        foreach ($order->items as $item) {
            if ($item->product === null || ! $item->product->is_active) {
                throw ValidationException::withMessages([
                    'cart' => "«{$item->product_name}» ya no esta disponible. Quitalo del carrito para seguir.",
                ]);
            }

            if ($item->variant === null || ! $item->variant->is_active) {
                throw ValidationException::withMessages([
                    'cart' => "La opcion «{$item->variant_name}» ya no esta disponible.",
                ]);
            }
        }
    }

    /**
     * N6 — si alguna linea es fisica hay que enviarla a alguna parte. Si todo
     * es digital, la direccion sobra y pedirla seria pedir datos personales
     * sin motivo.
     *
     * @param  array<string, string|null>  $direccion
     */
    private function guardDireccion(Order $order, array $direccion): void
    {
        $tieneFisica = $order->items->contains(
            fn (OrderItem $item) => $item->delivery_type === DeliveryType::Physical
        );

        if (! $tieneFisica) {
            return;
        }

        $obligatorios = [
            'shipping_name' => 'Hace falta un nombre para el envio.',
            'shipping_line1' => 'Hace falta una direccion.',
            'shipping_city' => 'Hace falta la ciudad.',
            'shipping_postal_code' => 'Hace falta el codigo postal.',
        ];

        $faltan = [];

        foreach ($obligatorios as $campo => $mensaje) {
            if (blank($direccion[$campo] ?? null)) {
                $faltan[$campo] = $mensaje;
            }
        }

        if ($faltan !== []) {
            throw ValidationException::withMessages($faltan);
        }
    }

    /** Recalcula cada linea contra la variante viva y devuelve los totales. */
    private function reprice(Order $order): OrderTotals
    {
        foreach ($order->items as $item) {
            $precio = $this->pricing->priceLine($item->variant, $item->quantity);

            $item->unit_price = $precio->unitPrice;
            $item->additional_copy_price = $precio->additionalCopyPrice;
            $item->line_total = $precio->lineTotal;
        }

        // Sobre la coleccion ya recalculada, sin volver a leer de la base.
        return $this->pricing->totalsFor($order);
    }

    private function hanCambiado(Order $order, OrderTotals $totales): bool
    {
        return $order->getOriginal('total') !== $totales->total;
    }

    private function persistirTotales(Order $order, OrderTotals $totales): void
    {
        DB::transaction(function () use ($order, $totales) {
            foreach ($order->items as $item) {
                $item->save();
            }

            $order->subtotal = $totales->subtotal;
            $order->shipping_total = $totales->shippingTotal;
            $order->total = $totales->total;
            $order->shipping_method_id = $totales->shippingMethodId;
            $order->save();
        });
    }
}
