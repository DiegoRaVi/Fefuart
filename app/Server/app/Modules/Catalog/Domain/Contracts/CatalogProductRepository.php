<?php

namespace App\Modules\Catalog\Domain\Contracts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CatalogProductRepository
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function findById(int $id): ?Product;

    public function create(array $attributes): Product;

    public function update(Product $product, array $attributes): Product;
}
