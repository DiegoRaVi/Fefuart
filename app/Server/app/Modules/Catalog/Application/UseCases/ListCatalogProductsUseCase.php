<?php

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Contracts\CatalogProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListCatalogProductsUseCase
{
    public function __construct(private readonly CatalogProductRepository $catalogProductRepository)
    {
    }

    public function execute(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->catalogProductRepository->paginate($filters, $perPage);
    }
}
