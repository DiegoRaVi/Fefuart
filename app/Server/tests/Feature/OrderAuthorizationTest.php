<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function orderTokenFor(User $user): string
{
    $response = test()->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()->assertJsonStructure(['token']);

    return $response->json('token');
}

test('user cannot update another users order', function () {
    $owner = User::factory()->create([
        'role' => 'user',
        'email' => 'order-owner@example.com',
        'password' => Hash::make('password123'),
    ]);

    $attacker = User::factory()->create([
        'role' => 'user',
        'email' => 'order-attacker@example.com',
        'password' => Hash::make('password123'),
    ]);

    $order = Order::create([
        'user_id' => $owner->id,
        'order_date' => now()->toDateString(),
        'status' => 'cart',
        'address' => 'Main street 1',
        'total' => 10,
    ]);

    $token = orderTokenFor($attacker);

    test()->patchJson('/api/orders/' . $order->id, [
        'total' => 999,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertForbidden();
});

test('order owner can move own cart order to pending', function () {
    $owner = User::factory()->create([
        'role' => 'user',
        'email' => 'cart-owner@example.com',
        'password' => Hash::make('password123'),
    ]);

    $order = Order::create([
        'user_id' => $owner->id,
        'order_date' => now()->toDateString(),
        'status' => 'cart',
        'address' => 'Main street 2',
        'total' => 20,
    ]);

    $token = orderTokenFor($owner);

    test()->patchJson('/api/orders/' . $order->id, [
        'status' => 'pending',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertOk();

    test()->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'pending',
    ]);
});

test('order owner cannot set non pending status', function () {
    $owner = User::factory()->create([
        'role' => 'user',
        'email' => 'owner-status@example.com',
        'password' => Hash::make('password123'),
    ]);

    $order = Order::create([
        'user_id' => $owner->id,
        'order_date' => now()->toDateString(),
        'status' => 'cart',
        'address' => 'Main street 3',
        'total' => 30,
    ]);

    $token = orderTokenFor($owner);

    test()->patchJson('/api/orders/' . $order->id, [
        'status' => 'shipped',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertStatus(422);
});

test('order owner cannot move non cart order to pending', function () {
    $owner = User::factory()->create([
        'role' => 'user',
        'email' => 'owner-noncart@example.com',
        'password' => Hash::make('password123'),
    ]);

    $order = Order::create([
        'user_id' => $owner->id,
        'order_date' => now()->toDateString(),
        'status' => 'paid',
        'address' => 'Main street 4',
        'total' => 40,
    ]);

    $token = orderTokenFor($owner);

    test()->patchJson('/api/orders/' . $order->id, [
        'status' => 'pending',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertStatus(422);
});

test('admin can update any order status', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin-order@example.com',
        'password' => Hash::make('password123'),
    ]);

    $owner = User::factory()->create([
        'role' => 'user',
        'email' => 'owner-admin-order@example.com',
        'password' => Hash::make('password123'),
    ]);

    $order = Order::create([
        'user_id' => $owner->id,
        'order_date' => now()->toDateString(),
        'status' => 'pending',
        'address' => 'Main street 5',
        'total' => 50,
    ]);

    $token = orderTokenFor($admin);

    test()->patchJson('/api/orders/' . $order->id, [
        'status' => 'shipped',
        'total' => 55,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertOk();

    test()->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'shipped',
        'total' => 55,
    ]);
});
