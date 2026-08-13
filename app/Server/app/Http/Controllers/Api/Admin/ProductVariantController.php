<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVariantRequest;
use App\Http\Requests\Admin\UpdateVariantRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Las variantes son donde vive el precio (N4) y donde se decide que entregas
 * admite el encargo (N7). Cambiar cualquiera de las dos cosas no toca el
 * historico: los pedidos guardan su propio snapshot.
 */
class ProductVariantController extends Controller
{
    public function store(StoreVariantRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();

        /** @var ProductVariant $variant */
        $variant = $product->variants()->create($data);
        $variant->shippingMethods()->sync($data['shipping_method_ids']);

        return ProductVariantResource::make($variant->load('shippingMethods'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateVariantRequest $request, ProductVariant $variant): ProductVariantResource
    {
        $data = $request->validated();

        $variant->update($data);

        if (isset($data['shipping_method_ids'])) {
            $variant->shippingMethods()->sync($data['shipping_method_ids']);
        }

        return ProductVariantResource::make($variant->load('shippingMethods'));
    }

    public function destroy(ProductVariant $variant): Response
    {
        $variant->delete();

        return response()->noContent();
    }
}
