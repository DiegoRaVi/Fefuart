<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDeliveryRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Notifications\DigitalDeliveryReady;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * D20, N11 — la entrega digital.
 *
 * Cierra un hueco heredado de v1: la variante «Digital» se vendia a 20 € con
 * entrega digital y no habia forma de entregarla. Se podia cobrar por algo
 * que el sistema no sabia dar.
 *
 * Sube la artista (ruta bajo `admin`) y descarga el cliente (ruta bajo
 * `auth:sanctum`, con OrderPolicy). Son dos rutas y no una porque son dos
 * permisos distintos sobre el mismo fichero.
 */
class DeliveryController extends Controller
{
    /**
     * La artista sube el archivo final de una linea.
     *
     * Subir **no** mueve el estado del pedido: uno de tres laminas necesita
     * tres subidas, y completarlo en la primera seria mentir. Lo pasa a
     * `completed` ella, cuando lo haya entregado todo.
     */
    public function store(
        StoreDeliveryRequest $request,
        Order $order,
        OrderItem $item,
        MediaStorageService $medios,
    ): OrderResource {
        $this->guardLineaEntregable($order, $item);

        $anterior = $item->deliveredMedia;

        $media = $medios->storeDelivery($request->file('file'), $request->user());

        $item->delivered_media_id = $media->id;
        $item->save();

        // Sustituir no puede dejar el anterior tirado en el disco. Se borra
        // despues de guardar el nuevo: si algo falla, es preferible un
        // huerfano —que el comando de limpieza recoge— a una linea apuntando
        // a un fichero que ya no esta.
        if ($anterior !== null) {
            $medios->delete($anterior);
        }

        // D10, D31 — dentro del mismo camino que hace el cambio, y una sola
        // vez por subida.
        $order->user->notify(new DigitalDeliveryReady($item));

        return OrderResource::make(
            $order->load(['user', 'items.referenceMedia', 'items.deliveredMedia', 'shippingMethod'])
        );
    }

    /**
     * El cliente descarga lo suyo.
     *
     * Siempre como **adjunto**. Nunca en linea: es lo que impide que el
     * navegador interprete el fichero en nuestro origen aunque algo hubiera
     * colado la lista blanca de la subida.
     */
    public function download(Request $request, Order $order, OrderItem $item): StreamedResponse
    {
        $this->authorize('download', $order);

        // 404 y no 403 cuando la linea no es de este pedido: que exista no es
        // asunto de quien pregunta.
        abort_unless($item->order_id === $order->id, 404);
        abort_if($item->delivered_media_id === null, 404);

        $media = $item->deliveredMedia;

        return Storage::disk('local')->download(
            $media->path,
            $this->nombreDeDescarga($item, $media->path),
        );
    }

    /**
     * @throws ValidationException
     */
    private function guardLineaEntregable(Order $order, OrderItem $item): void
    {
        abort_unless($item->order_id === $order->id, 404);

        // N11 — solo se entrega por descarga lo que se vendio como digital.
        if ($item->delivery_type !== DeliveryType::Digital) {
            throw ValidationException::withMessages([
                'file' => 'Esta linea no es una entrega digital.',
            ]);
        }

        // Entregar antes de cobrar seria regalar el trabajo.
        if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::InProgress, OrderStatus::Completed], true)) {
            throw ValidationException::withMessages([
                'file' => 'Este pedido todavia no esta pagado.',
            ]);
        }
    }

    /** Un nombre que el cliente reconozca, no el aleatorio del disco. */
    private function nombreDeDescarga(OrderItem $item, string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $base = preg_replace('/[^A-Za-z0-9]+/', '-', $item->product_name.'-'.$item->variant_name);

        return trim((string) $base, '-').'.'.$extension;
    }
}
