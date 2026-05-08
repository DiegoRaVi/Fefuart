<?php

namespace App\Modules\OrderManagement\Infrastructure\Persistence;

use App\Models\Order;
use App\Modules\OrderManagement\Domain\Contracts\CartRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentCartRepository implements CartRepository
{
    public function findActiveByUserId(int $userId): ?Order
    {
        return Order::where('user_id', $userId)
            ->where('status', 'cart')
            ->first();
    }

    public function createActiveForUser(int $userId): Order
    {
        return Order::create([
            'user_id' => $userId,
            'order_date' => now()->toDateString(),
            'status' => 'cart',
            'address' => 'No address provided',
            'total' => 0,
        ]);
    }

    public function refreshTotal(Order $cart): Order
    {
        $total = (float) $cart->products()->selectRaw('COALESCE(SUM(price * quantity), 0) as total')->value('total');

        $cart->update(['total' => $total]);

        return $cart->fresh();
    }

    public function checkout(Order $cart, string $address): Order
    {
        $cart->update([
            'status' => 'pending',
            'address' => $address,
        ]);

        return $cart->fresh();
    }

    public function getUserOrders(int $userId, int $perPage = 10, ?string $status = null): LengthAwarePaginator
    {
        $query = Order::where('user_id', $userId)
            ->where('status', '!=', 'cart')
            ->with('products')
            ->orderBy('order_date', 'desc')
            ->orderBy('id', 'desc');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }
}
