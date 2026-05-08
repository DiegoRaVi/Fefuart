<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function e2eV1TokenFor(User $user): string
{
    $response = test()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    return $response->json('data.token');
}

test('v1 end to end flow from catalog purchase to notification read works', function () {
    $assistant = User::factory()->create([
        'role' => 'assistant',
        'email' => 'journey-assistant@example.com',
        'password' => Hash::make('password123'),
    ]);

    $catalogProduct = Product::create([
        'name' => 'Journey Product',
        'description' => 'Producto para flujo completo',
        'price' => 20,
        'quantity' => 1,
        'category' => 'dibujo-encargo',
        'subcategory' => 'digital',
        'delivery_type' => 'digital',
        'delivery_time' => '7',
        'order_id' => null,
    ]);

    test()->postJson('/api/v1/auth/register', [
        'name' => 'Journey User',
        'email' => 'journey-user@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated()->assertJsonPath('success', true);

    $customerLogin = test()->postJson('/api/v1/auth/login', [
        'email' => 'journey-user@example.com',
        'password' => 'password123',
    ])->assertOk()->assertJsonPath('success', true);

    $customerToken = $customerLogin->json('data.token');
    $customerId = (int) $customerLogin->json('data.user.id');

    test()->postJson('/api/v1/cart', [], [
        'Authorization' => 'Bearer ' . $customerToken,
    ])->assertOk()->assertJsonPath('success', true);

    $addFromCatalog = test()->postJson('/api/v1/cart/items/from-catalog', [
        'product_id' => $catalogProduct->id,
        'quantity' => 2,
    ], [
        'Authorization' => 'Bearer ' . $customerToken,
    ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.item.quantity', 2)
        ->assertJsonPath('data.cart.total', 40);

    $orderId = (int) $addFromCatalog->json('data.cart.id');

    test()->postJson('/api/v1/cart/checkout', [
        'address' => 'Calle Journey 42',
    ], [
        'Authorization' => 'Bearer ' . $customerToken,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.order.id', $orderId)
        ->assertJsonPath('data.order.status', 'pending');

    $assistantToken = e2eV1TokenFor($assistant);

    test()->patchJson('/api/v1/backoffice/orders/' . $orderId . '/status', [
        'status' => 'paid',
    ], [
        'Authorization' => 'Bearer ' . $assistantToken,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.order.status', 'paid');

    test()->assertDatabaseHas('operational_notifications', [
        'user_id' => $customerId,
        'context_type' => 'order',
        'context_id' => $orderId,
        'new_status' => 'paid',
    ]);

    app('auth')->forgetGuards();

    $notifications = test()->getJson('/api/v1/notifications/my', [
        'Authorization' => 'Bearer ' . $customerToken,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.notifications')
        ->assertJsonPath('data.notifications.0.context_type', 'order')
        ->assertJsonPath('data.notifications.0.context_id', $orderId)
        ->assertJsonPath('data.notifications.0.previous_status', 'pending')
        ->assertJsonPath('data.notifications.0.new_status', 'paid');

    $notificationId = (int) $notifications->json('data.notifications.0.id');

    test()->patchJson('/api/v1/notifications/' . $notificationId . '/read', [], [
        'Authorization' => 'Bearer ' . $customerToken,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.notification.id', $notificationId)
        ->assertJsonPath('data.notification.is_read', true);

    test()->assertDatabaseHas('operational_notifications', [
        'id' => $notificationId,
        'user_id' => $customerId,
        'context_type' => 'order',
        'context_id' => $orderId,
        'new_status' => 'paid',
    ]);
});
