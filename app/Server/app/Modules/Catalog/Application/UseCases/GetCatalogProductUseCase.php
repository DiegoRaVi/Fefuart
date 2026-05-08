<?php

namespace App\Modules\Catalog\Application\UseCases;

use App\Models\Product;
use App\Modules\Catalog\Domain\Contracts\CatalogProductRepository;

final class GetCatalogProductUseCase
{
    public function __construct(private readonly CatalogProductRepository $catalogProductRepository)
    {
    }

    public function execute(int $id): ?Product
    {
        return $this->catalogProductRepository->findById($id);
    }
}
