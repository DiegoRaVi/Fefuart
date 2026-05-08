<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

function identityV1TokenFor(User $user): string
{
    $response = test()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    return $response->json('data.token');
}

test('v1 auth register always creates user role', function () {
    $payload = [
        'name' => 'V1 User',
        'email' => 'v1-user@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'admin',
    ];

    test()->postJson('/api/v1/auth/register', $payload)
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.role', 'user');

    test()->assertDatabaseHas('users', [
        'email' => 'v1-user@example.com',
        'role' => 'user',
    ]);
});

test('v1 auth login returns token and user', function () {
    User::factory()->create([
        'role' => 'user',
        'email' => 'v1-login@example.com',
        'password' => Hash::make('password123'),
    ]);

    test()->postJson('/api/v1/auth/login', [
        'email' => 'v1-login@example.com',
        'password' => 'password123',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'v1-login@example.com');
});

test('v1 auth me returns current authenticated user', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'v1-me@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = identityV1TokenFor($user);

    test()->getJson('/api/v1/auth/me', [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'v1-me@example.com');
});

test('v1 auth logout returns success envelope', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'v1-logout@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = identityV1TokenFor($user);

    test()->postJson('/api/v1/auth/logout', [], [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.message', 'Logged out successfully');
});
