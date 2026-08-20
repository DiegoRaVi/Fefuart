<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductImageRequest;
use App\Http\Resources\ProductResource;
use App\Models\ProductVariant;
use App\Services\MediaStorageService;

/**
 * La foto de un estilo concreto.
 *
 * Es hermano de `ProductImageController` y por los mismos motivos: endpoint
 * propio porque la imagen viaja como `multipart`, re-encodificada al entrar
 * (SEC-014) y al tamano de la galeria, que es para mirar en pantalla y no
 * para imprimir — al reves que la entrega digital (D20).
 *
 * Existe aparte de la del producto porque es lo unico que separa «Acuarela»
 * de «Diseno de moda» antes de encargar: son dos dibujos que no se parecen,
 * y con una sola foto por producto el cliente elegia a ciegas.
 *
 * Devuelve el **producto** entero y no la variante: quien sube la foto esta
 * mirando la ficha del producto en el backoffice, y es esa la que tiene que
 * refrescarse.
 */
class VariantImageController extends Controller
{
    public function store(
        StoreProductImageRequest $request,
        ProductVariant $variant,
        MediaStorageService $medios,
    ): ProductResource {
        $anterior = $variant->image;

        $imagenes = $medios->storeGalleryImage($request->file('file'), $request->user());

        $variant->image_media_id = $imagenes['media']->id;
        $variant->save();

        // Sustituir no puede dejar la anterior tirada en el disco. Se borra
        // despues de guardar la nueva: si algo falla, es preferible un
        // huerfano —que el comando de limpieza recoge— a una variante
        // apuntando a un fichero que ya no esta.
        if ($anterior !== null) {
            $medios->delete($anterior);
        }

        // La miniatura de la galeria no se usa aqui y se descarta: la ficha
        // pinta una sola imagen por estilo.
        $medios->delete($imagenes['thumbnail']);

        return ProductResource::make(
            $variant->product->fresh()->load(['image', 'variants.shippingMethods', 'variants.image']),
        );
    }
}
