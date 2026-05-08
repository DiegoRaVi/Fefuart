<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function catalogV1TokenFor(User $user): string
{
    $response = test()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    return $response->json('data.token');
}

test('v1 catalog list is public and returns only catalog products', function () {
    Product::create([
        'name' => 'Catalogo A',
        'description' => 'Visible',
        'price' => 30,
        'quantity' => 1,
        'category' => 'dibujo-encargo',
        'subcategory' => 'digital',
        'delivery_type' => 'digital',
        'delivery_time' => '7',
        'order_id' => null,
    ]);

    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'catalog-owner@example.com',
        'password' => Hash::make('password123'),
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_date' => now()->toDateString(),
        'status' => 'cart',
        'address' => 'Address',
        'total' => 0,
    ]);

    Product::create([
        'name' => 'No Catalogo',
        'description' => 'No visible',
        'price' => 40,
        'quantity' => 1,
        'category' => 'dibujo-encargo',
        'subcategory' => 'acuarela',
        'delivery_type' => 'physical',
        'delivery_time' => '10',
        'order_id' => $order->id,
    ]);

    test()->getJson('/api/v1/catalog/products')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.products');
});

test('v1 catalog supports category filters', function () {
    Product::create([
        'name' => 'Filtro Uno',
        'description' => null,
        'price' => 20,
        'quantity' => 1,
        'category' => 'ramo-flores',
        'subcategory' => 'preservado',
        'delivery_type' => 'physical',
        'delivery_time' => '10',
        'order_id' => null,
    ]);

    Product::create([
        'name' => 'Filtro Dos',
        'description' => null,
        'price' => 20,
        'quantity' => 1,
        'category' => 'dibujo-encargo',
        'subcategory' => 'digital',
        'delivery_type' => 'digital',
        'delivery_time' => '7',
        'order_id' => null,
    ]);

    test()->getJson('/api/v1/catalog/products?category=ramo-flores')
        ->assertOk()
        ->assertJsonCount(1, 'data.products')
        ->assertJsonPath('data.products.0.category', 'ramo-flores');
});

test('v1 catalog backoffice can create product', function () {
    $assistant = User::factory()->create([
        'role' => 'assistant',
        'email' => 'assistant-catalog@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = catalogV1TokenFor($assistant);

    $payload = [
        'name' => 'Nuevo Catalogo',
        'description' => 'Creado por assistant',
        'price' => 55,
        'quantity' => 1,
        'category' => 'dibujo-encargo',
        'subcategory' => 'acuarela',
        'delivery_type' => 'physical',
        'delivery_time' => '12',
    ];

    test()->postJson('/api/v1/catalog/products', $payload, [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.product.name', 'Nuevo Catalogo');

    test()->assertDatabaseHas('products', [
        'name' => 'Nuevo Catalogo',
        'order_id' => null,
    ]);
});

test('v1 catalog regular user cannot create product', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'user-catalog@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = catalogV1TokenFor($user);

    $payload = [
        'name' => 'Intento sin permiso',
        'description' => null,
        'price' => 30,
        'quantity' => 1,
        'category' => 'dibujo-encargo',
        'subcategory' => 'digital',
        'delivery_type' => 'digital',
        'delivery_time' => '7',
    ];

    test()->postJson('/api/v1/catalog/products', $payload, [
        'Authorization' => 'Bearer ' . $token,
    ])->assertStatus(403);
});

test('v1 catalog backoffice can update product', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin-catalog@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = catalogV1TokenFor($admin);

    $product = Product::create([
        'name' => 'Catalogo Editar',
        'description' => null,
        'price' => 25,
        'quantity' => 1,
        'category' => 'dibujo-encargo',
        'subcategory' => 'digital',
        'delivery_type' => 'digital',
        'delivery_time' => '7',
        'order_id' => null,
    ]);

    test()->patchJson('/api/v1/catalog/products/' . $product->id, [
        'price' => 35,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertOk()
        ->assertJsonPath('data.product.price', 35);

    test()->assertDatabaseHas('products', [
        'id' => $product->id,
        'price' => 35,
    ]);
});
