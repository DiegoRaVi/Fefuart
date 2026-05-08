<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

function loginAndGetToken(User $user, string $password = 'password123'): string
{
    $response = test()->postJson('/api/login', [
        'email' => $user->email,
        'password' => $password,
    ]);

    $response->assertOk()->assertJsonStructure(['token']);

    return $response->json('token');
}

test('register always creates user role even if admin role is sent', function () {
    $payload = [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'role' => 'admin',
    ];

    $response = test()->postJson('/api/register', $payload);

    $response->assertCreated();

    test()->assertDatabaseHas('users', [
        'email' => 'newuser@example.com',
        'role' => 'user',
    ]);
});

test('user cannot fetch another user profile by id', function () {
    $owner = User::factory()->create([
        'role' => 'user',
        'email' => 'owner@example.com',
        'password' => Hash::make('password123'),
    ]);

    $other = User::factory()->create([
        'role' => 'user',
        'email' => 'other@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = loginAndGetToken($owner);

    test()->getJson('/api/user/' . $other->id, [
        'Authorization' => 'Bearer ' . $token,
    ])->assertForbidden();
});

test('user can fetch own profile by id', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'self@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = loginAndGetToken($user);

    test()->getJson('/api/user/' . $user->id, [
        'Authorization' => 'Bearer ' . $token,
    ])->assertOk()->assertJson([
        'id' => $user->id,
        'email' => 'self@example.com',
        'role' => 'user',
    ]);
});

test('admin can fetch other user profile by id', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password123'),
    ]);

    $target = User::factory()->create([
        'role' => 'user',
        'email' => 'target@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = loginAndGetToken($admin);

    test()->getJson('/api/user/' . $target->id, [
        'Authorization' => 'Bearer ' . $token,
    ])->assertOk()->assertJson([
        'id' => $target->id,
        'email' => 'target@example.com',
    ]);
});
