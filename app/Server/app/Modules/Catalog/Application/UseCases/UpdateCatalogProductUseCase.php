<?php

namespace App\Modules\Catalog\Application\UseCases;

use App\Models\Product;
use App\Modules\Catalog\Application\DTOs\UpsertCatalogProductData;
use App\Modules\Catalog\Domain\Contracts\CatalogProductRepository;

final class UpdateCatalogProductUseCase
{
    public function __construct(private readonly CatalogProductRepository $catalogProductRepository)
    {
    }

    public function execute(Product $product, UpsertCatalogProductData $data): Product
    {
        return $this->catalogProductRepository->update($product, $data->toRepositoryPayload());
    }
}
