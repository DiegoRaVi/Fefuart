<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductImageRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\MediaStorageService;

/**
 * La foto del articulo en el catalogo.
 *
 * Endpoint propio y no un campo mas de `PATCH /admin/products/{id}`: una
 * imagen viaja como `multipart` y el resto del producto como JSON, y
 * mezclarlos obligaria a que editar el nombre reenviase la foto entera.
 *
 * Se re-encodifica como cualquier imagen que entre (SEC-014), y se guarda al
 * tamano de la galeria: es para mirar en pantalla, no para imprimir — al
 * reves que la entrega digital (D20).
 */
class ProductImageController extends Controller
{
    public function store(
        StoreProductImageRequest $request,
        Product $product,
        MediaStorageService $medios,
    ): ProductResource {
        $anterior = $product->image;

        $imagenes = $medios->storeGalleryImage($request->file('file'), $request->user());

        $product->image_media_id = $imagenes['media']->id;
        $product->save();

        // Sustituir no puede dejar la anterior tirada en el disco. Se borra
        // despues de guardar la nueva: si algo falla, es preferible un
        // huerfano —que el comando de limpieza recoge— a un producto
        // apuntando a un fichero que ya no esta.
        if ($anterior !== null) {
            $medios->delete($anterior);
        }

        // La miniatura de la galeria no se usa aqui y se descarta: el
        // catalogo pinta una sola imagen por producto.
        $medios->delete($imagenes['thumbnail']);

        return ProductResource::make($product->fresh()->load(['image', 'variants']));
    }
}
