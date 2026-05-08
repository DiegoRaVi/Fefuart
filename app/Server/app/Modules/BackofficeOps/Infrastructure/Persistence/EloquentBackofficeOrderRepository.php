<?php

namespace App\Modules\BackofficeOps\Infrastructure\Persistence;

use App\Models\Order;
use App\Modules\BackofficeOps\Domain\Contracts\BackofficeOrderRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentBackofficeOrderRepository implements BackofficeOrderRepository
{
    public function paginateByStatus(?string $status, int $perPage = 20): LengthAwarePaginator
    {
        $query = Order::with('products')->orderBy('order_date', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): ?Order
    {
        return Order::with('products')->find($id);
    }

    public function updateStatus(Order $order, string $status): Order
    {
        $order->update(['status' => $status]);

        return $order->fresh()->load('products');
    }
}
