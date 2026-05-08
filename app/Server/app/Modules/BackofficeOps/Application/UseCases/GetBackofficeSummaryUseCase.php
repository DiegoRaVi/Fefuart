<?php

namespace App\Modules\BackofficeOps\Application\UseCases;

use App\Models\Event;
use App\Models\Order;
use App\Models\Product;

final class GetBackofficeSummaryUseCase
{
    public function execute(): array
    {
        return [
            'orders' => [
                'cart' => Order::where('status', 'cart')->count(),
                'pending' => Order::where('status', 'pending')->count(),
                'paid' => Order::where('status', 'paid')->count(),
                'shipped' => Order::where('status', 'shipped')->count(),
                'cancelled' => Order::where('status', 'cancelled')->count(),
            ],
            'events' => [
                'pending' => Event::where('status', 'pending')->count(),
                'confirmed' => Event::where('status', 'confirmed')->count(),
                'rejected' => Event::where('status', 'rejected')->count(),
                'done' => Event::where('status', 'done')->count(),
            ],
            'catalog_products_total' => Product::whereNull('order_id')->count(),
            'generated_at' => now()->toISOString(),
        ];
    }
}
