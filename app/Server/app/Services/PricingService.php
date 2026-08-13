<?php

namespace App\Services;

use App\Enums\DeliveryType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Services\Pricing\LinePrice;
use App\Services\Pricing\OrderTotals;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * SEC-006 — el unico sitio donde se decide cuanto cuesta algo.
 *
 * En v1 el precio lo calculaba el navegador y el servidor lo aceptaba como
 * `'price' => 'required|numeric'`, de modo que un `POST /api/products` con
 * `price=0.01` colaba. Peor: las mismas reglas escritas en dos formularios
 * distintos daban precios distintos para el mismo caso.
 *
 * Aqui las reglas viven una sola vez, y el calculo es en centimos enteros:
 * con floats, 0.1 + 0.2 no es 0.3, y en un pedido de varias lineas ese error
 * acaba descuadrando el cobro.
 */
class PricingService
{
    /**
     * N4 — la primera copia paga el trabajo artistico; las siguientes, solo
     * la impresion.
     */
    public function priceLine(ProductVariant $variant, int $quantity): LinePrice
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException("La cantidad de una linea no puede ser {$quantity}.");
        }

        $unitCents = $this->toCents($variant->price);
        $copyCents = $this->toCents($variant->additional_copy_price);

        return new LinePrice(
            unitPrice: $this->toDecimal($unitCents),
            additionalCopyPrice: $this->toDecimal($copyCents),
            lineTotal: $this->toDecimal($unitCents + $copyCents * ($quantity - 1)),
        );
    }

    /**
     * N5/N6 — el envio se cobra una vez por pedido, y solo si alguna linea es
     * fisica. En v1 se sumaba por linea: tres articulos eran 15 EUR.
     */
    public function totalsFor(Order $order): OrderTotals
    {
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();

        $subtotalCents = $items->sum(fn (OrderItem $item) => $this->toCents($item->line_total));

        $shipping = $this->shippingMethodFor($items);
        $shippingCents = $shipping === null ? 0 : $this->toCents($shipping->price);

        return new OrderTotals(
            subtotal: $this->toDecimal($subtotalCents),
            shippingTotal: $this->toDecimal($shippingCents),
            total: $this->toDecimal($subtotalCents + $shippingCents),
            shippingMethodId: $shipping?->id,
        );
    }

    /**
     * El metodo de envio no lo elige el cliente: se deriva de las lineas. Un
     * pedido con al menos una linea fisica paga envio fisico; si todas son
     * digitales, paga el metodo digital, que vale 0.
     *
     * @param  Collection<int, OrderItem>  $items
     */
    private function shippingMethodFor(Collection $items): ?ShippingMethod
    {
        if ($items->isEmpty()) {
            return null;
        }

        $code = $items->contains(fn (OrderItem $item) => $item->delivery_type === DeliveryType::Physical)
            ? DeliveryType::Physical
            : DeliveryType::Digital;

        return ShippingMethod::query()->where('code', $code)->first();
    }

    /**
     * Convierte una cadena decimal a centimos sin pasar por float. `'40.5'`
     * son 4050 centimos; `'40'`, 4000.
     */
    private function toCents(string $amount): int
    {
        [$euros, $cents] = array_pad(explode('.', $amount, 2), 2, '0');

        return (int) $euros * 100 + (int) str_pad(substr($cents, 0, 2), 2, '0');
    }

    private function toDecimal(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
