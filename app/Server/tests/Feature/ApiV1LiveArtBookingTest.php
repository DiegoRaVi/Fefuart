<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

function liveArtV1TokenFor(User $user): string
{
    $response = test()->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()->assertJsonStructure(['token']);

    return $response->json('token');
}

test('v1 health endpoint returns contract envelope', function () {
    test()->getJson('/api/v1/health')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'service' => 'fefuart-api',
                'status' => 'ok',
            ],
            'meta' => [
                'version' => 'v1',
            ],
        ]);
});

test('authenticated user can create live art request in v1', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'liveart-v1@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = liveArtV1TokenFor($user);

    $payload = [
        'title' => 'Evento de prueba',
        'description' => 'Solicitud de live art para evento',
        'phone' => '600123123',
        'date' => now()->addDay()->toDateString(),
        'location' => 'Sevilla',
        'schedule' => 'morning',
    ];

    test()->postJson('/api/v1/live-art/requests', $payload, [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Evento de prueba')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('meta.version', 'v1');

    test()->assertDatabaseHas('events', [
        'title' => 'Evento de prueba',
        'user_id' => $user->id,
        'status' => 'pending',
    ]);
});
