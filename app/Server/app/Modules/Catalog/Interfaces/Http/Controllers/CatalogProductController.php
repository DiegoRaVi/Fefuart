<?php

namespace App\Modules\Catalog\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\DTOs\UpsertCatalogProductData;
use App\Modules\Catalog\Application\UseCases\CreateCatalogProductUseCase;
use App\Modules\Catalog\Application\UseCases\GetCatalogProductUseCase;
use App\Modules\Catalog\Application\UseCases\ListCatalogProductsUseCase;
use App\Modules\Catalog\Application\UseCases\UpdateCatalogProductUseCase;
use App\Modules\Catalog\Interfaces\Http\Requests\StoreCatalogProductRequest;
use App\Modules\Catalog\Interfaces\Http\Requests\UpdateCatalogProductRequest;
use App\Modules\Catalog\Interfaces\Http\Resources\CatalogProductResource;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogProductController extends Controller
{
    public function __construct(
        private readonly ListCatalogProductsUseCase $listCatalogProductsUseCase,
        private readonly GetCatalogProductUseCase $getCatalogProductUseCase,
        private readonly CreateCatalogProductUseCase $createCatalogProductUseCase,
        private readonly UpdateCatalogProductUseCase $updateCatalogProductUseCase,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'category' => $request->query('category'),
            'subcategory' => $request->query('subcategory'),
        ];

        $products = $this->listCatalogProductsUseCase->execute($filters, (int) $request->query('per_page', 20));

        return ApiResponse::success([
            'products' => CatalogProductResource::collection($products->items())->resolve(),
        ], 200, [
            'version' => 'v1',
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $product = $this->getCatalogProductUseCase->execute($id);

        if (!$product) {
            return ApiResponse::error('RESOURCE_NOT_FOUND', 'Catalog product not found', 404);
        }

        return ApiResponse::success([
            'product' => (new CatalogProductResource($product))->resolve(),
        ], 200, ['version' => 'v1']);
    }

    public function store(StoreCatalogProductRequest $request): JsonResponse
    {
        $dto = UpsertCatalogProductData::fromArray($request->validated());
        $product = $this->createCatalogProductUseCase->execute($dto);

        return ApiResponse::success([
            'product' => (new CatalogProductResource($product))->resolve(),
        ], 201, ['version' => 'v1']);
    }

    public function update(UpdateCatalogProductRequest $request, int $id): JsonResponse
    {
        $product = $this->getCatalogProductUseCase->execute($id);

        if (!$product) {
            return ApiResponse::error('RESOURCE_NOT_FOUND', 'Catalog product not found', 404);
        }

        $dto = UpsertCatalogProductData::fromArray(array_merge($product->toArray(), $request->validated()));
        $updatedProduct = $this->updateCatalogProductUseCase->execute($product, $dto);

        return ApiResponse::success([
            'product' => (new CatalogProductResource($updatedProduct))->resolve(),
        ], 200, ['version' => 'v1']);
    }
}
