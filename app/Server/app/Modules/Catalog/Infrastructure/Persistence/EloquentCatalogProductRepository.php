<?php

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Models\Product;
use App\Modules\Catalog\Domain\Contracts\CatalogProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentCatalogProductRepository implements CatalogProductRepository
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Product::query()->whereNull('order_id');

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['subcategory'])) {
            $query->where('subcategory', $filters['subcategory']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function findById(int $id): ?Product
    {
        return Product::whereNull('order_id')->find($id);
    }

    public function create(array $attributes): Product
    {
        return Product::create($attributes);
    }

    public function update(Product $product, array $attributes): Product
    {
        $product->update($attributes);

        return $product->fresh();
    }
}
