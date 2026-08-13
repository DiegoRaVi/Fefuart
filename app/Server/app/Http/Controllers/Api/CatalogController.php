<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * El catalogo publico. En v1 no existia: `galeria.html` era HTML estatico y
 * ninguna tabla decia que se podia encargar ni a que precio (DB-002).
 *
 * Sin autenticacion: cualquiera puede ver que se vende. Encargar si exige
 * cuenta (N18).
 */
class CatalogController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        // BUG-008 — v1 devolvia 404 cuando la coleccion venia vacia. Una
        // lista sin elementos es un 200 con lista vacia.
        $products = Product::query()
            ->active()
            ->with($this->activeVariants())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ProductResource::collection($products);
    }

    /**
     * PERF-001 — las variantes y sus metodos de envio van eager-loaded. El
     * backoffice de v1 hacia una peticion por fila renderizada.
     */
    public function show(Product $product): ProductResource
    {
        // Un producto desactivado no esta publicado: 404, no 403. No hay
        // nada que autorizar, sencillamente no forma parte del catalogo.
        abort_unless($product->is_active, 404);

        return ProductResource::make($product->load($this->activeVariants()));
    }

    /**
     * @return array<string, \Closure>
     */
    private function activeVariants(): array
    {
        return [
            'variants' => fn ($query) => $query
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->with('shippingMethods', fn ($q) => $q->orderBy('sort_order')),
        ];
    }
}
