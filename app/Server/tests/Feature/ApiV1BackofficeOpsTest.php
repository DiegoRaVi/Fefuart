<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function backofficeV1TokenFor(User $user): string
{
    $response = test()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    return $response->json('data.token');
}

test('v1 backoffice endpoints require backoffice role', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'backoffice-forbidden@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = backofficeV1TokenFor($user);

    test()->getJson('/api/v1/backoffice/orders', [
        'Authorization' => 'Bearer ' . $token,
    ])->assertStatus(403);

    test()->getJson('/api/v1/backoffice/events', [
        'Authorization' => 'Bearer ' . $token,
    ])->assertStatus(403);

    test()->getJson('/api/v1/backoffice/summary', [
        'Authorization' => 'Bearer ' . $token,
    ])->assertStatus(403);
});

test('v1 assistant can read backoffice summary metrics', function () {
    $assistant = User::factory()->create([
        'role' => 'assistant',
        'email' => 'backoffice-summary-assistant@example.com',
        'password' => Hash::make('password123'),
    ]);

    $customer = User::factory()->create([
        'role' => 'user',
        'email' => 'backoffice-summary-customer@example.com',
        'password' => Hash::make('password123'),
    ]);

    $order = Order::create([
        'user_id' => $customer->id,
        'order_date' => now()->toDateString(),
        'status' => 'pending',
        'address' => 'Summary street 1',
        'total' => 75,
    ]);

    Event::create([
        'title' => 'Evento Summary',
        'description' => 'Resumen operativo',
        'phone' => '600000000',
        'date' => now()->addDay()->toDateString(),
        'location' => 'Valencia',
        'schedule' => 'morning',
        'status' => 'confirmed',
        'user_id' => $customer->id,
    ]);

    Product::create([
        'name' => 'Catalog Summary',
        'description' => null,
        'price' => 20,
        'quantity' => 1,
        'category' => 'dibujo-encargo',
        'subcategory' => 'digital',
        'delivery_type' => 'digital',
        'delivery_time' => '7',
        'order_id' => null,
    ]);

    Product::create([
        'name' => 'Order Item Summary',
        'description' => null,
        'price' => 30,
        'quantity' => 1,
        'category' => 'dibujo-encargo',
        'subcategory' => 'digital',
        'delivery_type' => 'digital',
        'delivery_time' => '7',
        'order_id' => $order->id,
    ]);

    $token = backofficeV1TokenFor($assistant);

    test()->getJson('/api/v1/backoffice/summary', [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.summary.orders.pending', 1)
        ->assertJsonPath('data.summary.events.confirmed', 1)
        ->assertJsonPath('data.summary.catalog_products_total', 1);
});

test('v1 assistant can list and update orders', function () {
    $assistant = User::factory()->create([
        'role' => 'assistant',
        'email' => 'backoffice-assistant-orders@example.com',
        'password' => Hash::make('password123'),
    ]);

    $customer = User::factory()->create([
        'role' => 'user',
        'email' => 'backoffice-customer-orders@example.com',
        'password' => Hash::make('password123'),
    ]);

    $order = Order::create([
        'user_id' => $customer->id,
        'order_date' => now()->toDateString(),
        'status' => 'pending',
        'address' => 'Calle Backoffice 1',
        'total' => 120,
    ]);

    $token = backofficeV1TokenFor($assistant);

    test()->getJson('/api/v1/backoffice/orders?status=pending', [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.orders')
        ->assertJsonPath('data.orders.0.id', $order->id)
        ->assertJsonPath('data.orders.0.status', 'pending');

    test()->patchJson('/api/v1/backoffice/orders/' . $order->id . '/status', [
        'status' => 'paid',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.order.id', $order->id)
        ->assertJsonPath('data.order.status', 'paid');

    test()->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'paid',
    ]);

    test()->assertDatabaseHas('operational_notifications', [
        'user_id' => $customer->id,
        'actor_user_id' => $assistant->id,
        'context_type' => 'order',
        'context_id' => $order->id,
        'previous_status' => 'pending',
        'new_status' => 'paid',
    ]);
});

test('v1 assistant can list and update events', function () {
    $assistant = User::factory()->create([
        'role' => 'assistant',
        'email' => 'backoffice-assistant-events@example.com',
        'password' => Hash::make('password123'),
    ]);

    $customer = User::factory()->create([
        'role' => 'user',
        'email' => 'backoffice-customer-events@example.com',
        'password' => Hash::make('password123'),
    ]);

    $event = Event::create([
        'title' => 'Evento Backoffice',
        'description' => 'Evento para validar operaciones internas',
        'phone' => '600123123',
        'date' => now()->addDay()->toDateString(),
        'location' => 'Sevilla',
        'schedule' => 'morning',
        'status' => 'pending',
        'user_id' => $customer->id,
    ]);

    $token = backofficeV1TokenFor($assistant);

    test()->getJson('/api/v1/backoffice/events?status=pending', [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.events')
        ->assertJsonPath('data.events.0.id', $event->id)
        ->assertJsonPath('data.events.0.status', 'pending');

    test()->patchJson('/api/v1/backoffice/events/' . $event->id . '/status', [
        'status' => 'confirmed',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.event.id', $event->id)
        ->assertJsonPath('data.event.status', 'confirmed');

    test()->assertDatabaseHas('events', [
        'id' => $event->id,
        'status' => 'confirmed',
    ]);

    test()->assertDatabaseHas('operational_notifications', [
        'user_id' => $customer->id,
        'actor_user_id' => $assistant->id,
        'context_type' => 'event',
        'context_id' => $event->id,
        'previous_status' => 'pending',
        'new_status' => 'confirmed',
    ]);
});

test('v1 backoffice validates status payloads on update', function () {
    $assistant = User::factory()->create([
        'role' => 'assistant',
        'email' => 'backoffice-assistant-validation@example.com',
        'password' => Hash::make('password123'),
    ]);

    $customer = User::factory()->create([
        'role' => 'user',
        'email' => 'backoffice-customer-validation@example.com',
        'password' => Hash::make('password123'),
    ]);

    $order = Order::create([
        'user_id' => $customer->id,
        'order_date' => now()->toDateString(),
        'status' => 'pending',
        'address' => 'Calle Backoffice 2',
        'total' => 40,
    ]);

    $event = Event::create([
        'title' => 'Evento Validacion',
        'description' => null,
        'phone' => null,
        'date' => now()->addDay()->toDateString(),
        'location' => 'Madrid',
        'schedule' => 'evening',
        'status' => 'pending',
        'user_id' => $customer->id,
    ]);

    $token = backofficeV1TokenFor($assistant);

    test()->patchJson('/api/v1/backoffice/orders/' . $order->id . '/status', [
        'status' => 'invalid-status',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertStatus(422);

    test()->patchJson('/api/v1/backoffice/events/' . $event->id . '/status', [
        'status' => 'invalid-status',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertStatus(422);
});

test('v1 backoffice returns not found for missing resources', function () {
    $assistant = User::factory()->create([
        'role' => 'assistant',
        'email' => 'backoffice-assistant-notfound@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = backofficeV1TokenFor($assistant);

    test()->patchJson('/api/v1/backoffice/orders/999999/status', [
        'status' => 'paid',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertStatus(404)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

    test()->patchJson('/api/v1/backoffice/events/999999/status', [
        'status' => 'confirmed',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertStatus(404)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});
