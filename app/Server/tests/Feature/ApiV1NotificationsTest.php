<?php

use App\Models\OperationalNotification;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function notificationsV1TokenFor(User $user): string
{
    $response = test()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    return $response->json('data.token');
}

test('v1 notification endpoints require authentication', function () {
    test()->getJson('/api/v1/notifications/my')->assertStatus(401);

    test()->patchJson('/api/v1/notifications/1/read')->assertStatus(401);
});

test('v1 user can list only own notifications', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'notifications-owner@example.com',
        'password' => Hash::make('password123'),
    ]);

    $otherUser = User::factory()->create([
        'role' => 'user',
        'email' => 'notifications-other@example.com',
        'password' => Hash::make('password123'),
    ]);

    OperationalNotification::create([
        'user_id' => $user->id,
        'actor_user_id' => null,
        'context_type' => 'order',
        'context_id' => 10,
        'channel' => 'in_app',
        'title' => 'Notificacion propia',
        'body' => 'Solo debe verla su dueño',
        'previous_status' => 'pending',
        'new_status' => 'paid',
        'payload' => ['order_id' => 10],
    ]);

    OperationalNotification::create([
        'user_id' => $otherUser->id,
        'actor_user_id' => null,
        'context_type' => 'event',
        'context_id' => 20,
        'channel' => 'in_app',
        'title' => 'Notificacion ajena',
        'body' => 'No debe aparecer',
        'previous_status' => 'pending',
        'new_status' => 'confirmed',
        'payload' => ['event_id' => 20],
    ]);

    $token = notificationsV1TokenFor($user);

    test()->getJson('/api/v1/notifications/my', [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.notifications')
        ->assertJsonPath('data.notifications.0.title', 'Notificacion propia');
});

test('v1 user can mark own notification as read', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'notifications-read@example.com',
        'password' => Hash::make('password123'),
    ]);

    $notification = OperationalNotification::create([
        'user_id' => $user->id,
        'actor_user_id' => null,
        'context_type' => 'order',
        'context_id' => 11,
        'channel' => 'in_app',
        'title' => 'Pendiente de lectura',
        'body' => 'Debe marcarse como leida',
        'previous_status' => 'pending',
        'new_status' => 'paid',
        'payload' => ['order_id' => 11],
    ]);

    $token = notificationsV1TokenFor($user);

    test()->patchJson('/api/v1/notifications/' . $notification->id . '/read', [], [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.notification.id', $notification->id)
        ->assertJsonPath('data.notification.is_read', true);

    test()->assertDatabaseMissing('operational_notifications', [
        'id' => $notification->id,
        'read_at' => null,
    ]);
});

test('v1 user cannot mark another users notification as read', function () {
    $owner = User::factory()->create([
        'role' => 'user',
        'email' => 'notifications-owner-block@example.com',
        'password' => Hash::make('password123'),
    ]);

    $intruder = User::factory()->create([
        'role' => 'user',
        'email' => 'notifications-intruder@example.com',
        'password' => Hash::make('password123'),
    ]);

    $notification = OperationalNotification::create([
        'user_id' => $owner->id,
        'actor_user_id' => null,
        'context_type' => 'event',
        'context_id' => 22,
        'channel' => 'in_app',
        'title' => 'Privada',
        'body' => 'No debe poder modificarla',
        'previous_status' => 'pending',
        'new_status' => 'confirmed',
        'payload' => ['event_id' => 22],
    ]);

    $token = notificationsV1TokenFor($intruder);

    test()->patchJson('/api/v1/notifications/' . $notification->id . '/read', [], [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertStatus(404)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});
