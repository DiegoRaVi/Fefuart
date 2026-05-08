<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\Order;
use App\Models\Product;

function cartV1TokenFor(User $user): string
{
    $response = test()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    return $response->json('data.token');
}

test('v1 cart endpoints require authentication', function () {
    test()->getJson('/api/v1/cart')->assertStatus(401);
});

test('v1 can create or get cart for authenticated user', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'cart-v1@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = cartV1TokenFor($user);

    $first = test()->postJson('/api/v1/cart', [], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertOk()->assertJsonPath('success', true);

    $firstCartId = $first->json('data.cart.id');

    $second = test()->postJson('/api/v1/cart', [], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertOk()->assertJsonPath('success', true);

    $secondCartId = $second->json('data.cart.id');

    expect($secondCartId)->toBe($firstCartId);

    test()->getJson('/api/v1/cart', [
        'Authorization' => 'Bearer ' . $token,
    ])->assertOk()->assertJsonPath('data.cart.status', 'cart');
});

test('v1 can add item to cart and recalculate total', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'cart-item-v1@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = cartV1TokenFor($user);

    $payload = [
        'name' => 'Retrato digital',
        'price' => 45,
        'quantity' => 1,
        'description' => 'Retrato personalizado',
        'category' => 'dibujo-encargo',
        'subcategory' => 'digital',
        'delivery_type' => 'digital',
        'delivery_time' => '7',
    ];

    $response = test()->postJson('/api/v1/cart/items', $payload, [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.item.name', 'Retrato digital');

    $cartId = $response->json('data.cart.id');

    test()->assertDatabaseHas('orders', [
        'id' => $cartId,
        'user_id' => $user->id,
        'status' => 'cart',
        'total' => 45,
    ]);

    test()->assertDatabaseHas('products', [
        'order_id' => $cartId,
        'name' => 'Retrato digital',
        'price' => 45,
    ]);
});

test('v1 checkout validates business rule for empty cart', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'cart-checkout-empty@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = cartV1TokenFor($user);

    test()->postJson('/api/v1/cart', [], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertOk();

    test()->postJson('/api/v1/cart/checkout', [
        'address' => 'Calle Falsa 123',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
});

test('v1 can checkout cart and list my orders', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'cart-checkout-v1@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = cartV1TokenFor($user);

    $payload = [
        'name' => 'Ilustracion enmarcada',
        'price' => 60,
        'quantity' => 1,
        'description' => 'Pedido para checkout',
        'category' => 'dibujo-encargo',
        'subcategory' => 'acuarela',
        'delivery_type' => 'physical',
        'delivery_time' => '10',
    ];

    $cartResponse = test()->postJson('/api/v1/cart/items', $payload, [
        'Authorization' => 'Bearer ' . $token,
    ])->assertCreated();

    $cartId = $cartResponse->json('data.cart.id');

    test()->postJson('/api/v1/cart/checkout', [
        'address' => 'Avenida Principal 45',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.order.status', 'pending')
        ->assertJsonPath('data.order.address', 'Avenida Principal 45');

    test()->assertDatabaseHas('orders', [
        'id' => $cartId,
        'status' => 'pending',
        'address' => 'Avenida Principal 45',
    ]);

    test()->getJson('/api/v1/orders/my', [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.orders.0.status', 'pending');
});

test('v1 can add item from catalog reference and uses quantity for cart total', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'cart-from-catalog@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = cartV1TokenFor($user);

    $catalogProduct = Product::create([
        'name' => 'Producto Catalogo',
        'description' => 'Catalog item',
        'price' => 20,
        'quantity' => 1,
        'category' => 'dibujo-encargo',
        'subcategory' => 'digital',
        'delivery_type' => 'digital',
        'delivery_time' => '7',
        'order_id' => null,
    ]);

    $response = test()->postJson('/api/v1/cart/items/from-catalog', [
        'product_id' => $catalogProduct->id,
        'quantity' => 3,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.item.name', 'Producto Catalogo')
        ->assertJsonPath('data.item.quantity', 3)
        ->assertJsonPath('data.cart.total', 60);

    $cartId = $response->json('data.cart.id');

    test()->assertDatabaseHas('orders', [
        'id' => $cartId,
        'total' => 60,
    ]);
});

test('v1 add from catalog returns not found for unknown product', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'cart-from-catalog-notfound@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = cartV1TokenFor($user);

    test()->postJson('/api/v1/cart/items/from-catalog', [
        'product_id' => 999999,
        'quantity' => 1,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertStatus(404)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});

test('v1 can update cart item quantity and recalculate cart total', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'cart-update-line@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = cartV1TokenFor($user);

    $createItem = test()->postJson('/api/v1/cart/items', [
        'name' => 'Lamina personalizada',
        'price' => 18,
        'quantity' => 1,
        'description' => 'Inicial',
        'category' => 'dibujo-encargo',
        'subcategory' => 'digital',
        'delivery_type' => 'digital',
        'delivery_time' => '5',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertCreated();

    $itemId = $createItem->json('data.item.id');
    $cartId = $createItem->json('data.cart.id');

    test()->patchJson('/api/v1/cart/items/' . $itemId, [
        'quantity' => 4,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.item.quantity', 4)
        ->assertJsonPath('data.cart.total', 72);

    test()->assertDatabaseHas('products', [
        'id' => $itemId,
        'order_id' => $cartId,
        'quantity' => 4,
    ]);

    test()->assertDatabaseHas('orders', [
        'id' => $cartId,
        'status' => 'cart',
        'total' => 72,
    ]);
});

test('v1 can remove cart line and recalculate cart total', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'cart-remove-line@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = cartV1TokenFor($user);

    $firstItem = test()->postJson('/api/v1/cart/items', [
        'name' => 'Linea A',
        'price' => 20,
        'quantity' => 2,
        'description' => 'Primera linea',
        'category' => 'dibujo-encargo',
        'subcategory' => 'digital',
        'delivery_type' => 'digital',
        'delivery_time' => '7',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertCreated();

    $cartId = $firstItem->json('data.cart.id');
    $firstItemId = $firstItem->json('data.item.id');

    test()->postJson('/api/v1/cart/items', [
        'name' => 'Linea B',
        'price' => 10,
        'quantity' => 1,
        'description' => 'Segunda linea',
        'category' => 'dibujo-encargo',
        'subcategory' => 'digital',
        'delivery_type' => 'digital',
        'delivery_time' => '7',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertCreated();

    test()->deleteJson('/api/v1/cart/items/' . $firstItemId, [], [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.cart.total', 10)
        ->assertJsonCount(1, 'data.cart.items');

    test()->assertDatabaseMissing('products', [
        'id' => $firstItemId,
    ]);

    test()->assertDatabaseHas('orders', [
        'id' => $cartId,
        'total' => 10,
    ]);
});

test('v1 can filter my orders by status and customize page size', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'orders-filter-v1@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = cartV1TokenFor($user);

    $firstCheckout = test()->postJson('/api/v1/cart/items', [
        'name' => 'Pedido para paid',
        'price' => 25,
        'quantity' => 1,
        'description' => 'Order paid',
        'category' => 'dibujo-encargo',
        'subcategory' => 'digital',
        'delivery_type' => 'digital',
        'delivery_time' => '7',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertCreated();

    $firstOrder = test()->postJson('/api/v1/cart/checkout', [
        'address' => 'Direccion order paid',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertOk();

    $firstOrderId = (int) $firstOrder->json('data.order.id');
    Order::query()->whereKey($firstOrderId)->update(['status' => 'paid']);

    test()->postJson('/api/v1/cart/items', [
        'name' => 'Pedido pendiente',
        'price' => 15,
        'quantity' => 1,
        'description' => 'Order pending',
        'category' => 'dibujo-encargo',
        'subcategory' => 'digital',
        'delivery_type' => 'digital',
        'delivery_time' => '7',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertCreated();

    test()->postJson('/api/v1/cart/checkout', [
        'address' => 'Direccion order pending',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertOk();

    test()->getJson('/api/v1/orders/my?status=paid&per_page=5', [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.filters.status', 'paid')
        ->assertJsonPath('meta.pagination.per_page', 5)
        ->assertJsonPath('data.orders.0.id', $firstOrderId)
        ->assertJsonPath('data.orders.0.status', 'paid')
        ->assertJsonCount(1, 'data.orders');
});

test('v1 my orders are sorted by newest id when order_date is equal', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'orders-sort-v1@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = cartV1TokenFor($user);

    test()->postJson('/api/v1/cart/items', [
        'name' => 'Pedido antiguo',
        'price' => 30,
        'quantity' => 1,
        'description' => 'old order',
        'category' => 'dibujo-encargo',
        'subcategory' => 'digital',
        'delivery_type' => 'digital',
        'delivery_time' => '7',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertCreated();

    $firstCheckout = test()->postJson('/api/v1/cart/checkout', [
        'address' => 'Direccion orden antigua',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertOk();

    $firstOrderId = (int) $firstCheckout->json('data.order.id');

    test()->postJson('/api/v1/cart/items', [
        'name' => 'Pedido nuevo',
        'price' => 55,
        'quantity' => 1,
        'description' => 'new order',
        'category' => 'dibujo-encargo',
        'subcategory' => 'digital',
        'delivery_type' => 'digital',
        'delivery_time' => '7',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertCreated();

    $secondCheckout = test()->postJson('/api/v1/cart/checkout', [
        'address' => 'Direccion orden nueva',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertOk();

    $secondOrderId = (int) $secondCheckout->json('data.order.id');

    expect($secondOrderId)->toBeGreaterThan($firstOrderId);

    test()->getJson('/api/v1/orders/my?status=all&per_page=10', [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.orders.0.id', $secondOrderId)
        ->assertJsonPath('data.orders.1.id', $firstOrderId);
});
