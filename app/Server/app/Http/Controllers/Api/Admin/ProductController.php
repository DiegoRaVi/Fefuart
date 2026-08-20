<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * D5 — el catalogo se gestiona desde el backoffice, no desde el codigo. En
 * v1 los precios vivian en el `<script>` de cada formulario, asi que cambiar
 * uno exigia editar HTML.
 *
 * Todas las rutas van tras `auth:sanctum` + `admin`. La comprobacion de rol
 * esta en el middleware y solo ahi: v1 la repetia en linea en una decena de
 * metodos que ya estaban tras el middleware (ARCH-002).
 */
class ProductController extends Controller
{
    /**
     * A diferencia del catalogo publico, aqui salen tambien los
     * desactivados: es lo que hay que ver para reactivarlos.
     */
    public function index(): AnonymousResourceCollection
    {
        return ProductResource::collection(
            Product::query()
                ->with(['image', 'variants.shippingMethods', 'variants.image'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(25)
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::query()->create($request->validated());

        return ProductResource::make($product->load(['image', 'variants.shippingMethods', 'variants.image']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Product $product): ProductResource
    {
        return ProductResource::make($product->load(['image', 'variants.shippingMethods', 'variants.image']));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        // `fill` con lo validado: un `role_id` en el cuerpo no tiene por
        // donde entrar, ni aunque el modelo fuese otro.
        $product->update($request->validated());

        return ProductResource::make($product->load(['image', 'variants.shippingMethods', 'variants.image']));
    }

    /**
     * DB-004 — borrado logico. El pedido que compro este producto conserva su
     * nombre en el snapshot, asi que el historico no se rompe.
     */
    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }
}
