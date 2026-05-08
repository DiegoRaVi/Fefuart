<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

function mediaV1TokenFor(User $user): string
{
    $response = test()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    return $response->json('data.token');
}

function fakePngUpload(string $name = 'upload.png'): UploadedFile
{
    $pngContent = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2Wf9kAAAAASUVORK5CYII='
    );

    return UploadedFile::fake()->createWithContent($name, $pngContent ?: 'png');
}

test('v1 media upload and delete require authentication', function () {
    $file = fakePngUpload('unauth.png');

    test()->post('/api/v1/media/upload', [
        'file' => $file,
    ])->assertStatus(401);

    test()->deleteJson('/api/v1/media/1')->assertStatus(401);
});

test('v1 authenticated user can upload media and public metadata is accessible', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'media-upload@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = mediaV1TokenFor($user);

    $response = test()->post('/api/v1/media/upload', [
        'file' => fakePngUpload('avatar.png'),
        'context_type' => 'general',
        'context_id' => 1,
        'visibility' => 'public',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.asset.visibility', 'public');

    $assetId = $response->json('data.asset.id');
    $path = $response->json('data.asset.path');

    expect(Storage::disk('public')->exists($path))->toBeTrue();

    test()->assertDatabaseHas('media_assets', [
        'id' => $assetId,
        'user_id' => $user->id,
        'visibility' => 'public',
    ]);

    test()->getJson('/api/v1/media/' . $assetId)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.asset.id', $assetId);
});

test('v1 private media requires authentication and ownership or backoffice role', function () {
    Storage::fake('public');

    $owner = User::factory()->create([
        'role' => 'user',
        'email' => 'media-private-owner@example.com',
        'password' => Hash::make('password123'),
    ]);

    $otherUser = User::factory()->create([
        'role' => 'user',
        'email' => 'media-private-other@example.com',
        'password' => Hash::make('password123'),
    ]);

    $assistant = User::factory()->create([
        'role' => 'assistant',
        'email' => 'media-private-assistant@example.com',
        'password' => Hash::make('password123'),
    ]);

    $ownerToken = mediaV1TokenFor($owner);
    $otherToken = mediaV1TokenFor($otherUser);
    $assistantToken = mediaV1TokenFor($assistant);

    $upload = test()->post('/api/v1/media/upload', [
        'file' => fakePngUpload('private.png'),
        'visibility' => 'private',
    ], [
        'Authorization' => 'Bearer ' . $ownerToken,
    ])->assertCreated();

    $assetId = $upload->json('data.asset.id');

    test()->getJson('/api/v1/media/' . $assetId)->assertStatus(401);

    test()->getJson('/api/v1/media/' . $assetId, [
        'Authorization' => 'Bearer ' . $otherToken,
    ])->assertStatus(403);

    test()->getJson('/api/v1/media/' . $assetId, [
        'Authorization' => 'Bearer ' . $assistantToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.asset.id', $assetId);
});

test('v1 owner can delete own media asset', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'role' => 'user',
        'email' => 'media-delete-owner@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = mediaV1TokenFor($user);

    $upload = test()->post('/api/v1/media/upload', [
        'file' => fakePngUpload('delete-me.png'),
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertCreated();

    $assetId = $upload->json('data.asset.id');
    $path = $upload->json('data.asset.path');

    test()->deleteJson('/api/v1/media/' . $assetId, [], [
        'Authorization' => 'Bearer ' . $token,
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Storage::disk('public')->exists($path))->toBeFalse();

    test()->assertDatabaseMissing('media_assets', [
        'id' => $assetId,
    ]);
});

test('v1 non owner user cannot delete another users media asset', function () {
    Storage::fake('public');

    $owner = User::factory()->create([
        'role' => 'user',
        'email' => 'media-delete-owner-two@example.com',
        'password' => Hash::make('password123'),
    ]);

    $intruder = User::factory()->create([
        'role' => 'user',
        'email' => 'media-delete-intruder@example.com',
        'password' => Hash::make('password123'),
    ]);

    $ownerToken = mediaV1TokenFor($owner);
    $intruderToken = mediaV1TokenFor($intruder);

    $upload = test()->post('/api/v1/media/upload', [
        'file' => fakePngUpload('cannot-delete.png'),
    ], [
        'Authorization' => 'Bearer ' . $ownerToken,
    ])->assertCreated();

    $assetId = $upload->json('data.asset.id');

    test()->deleteJson('/api/v1/media/' . $assetId, [], [
        'Authorization' => 'Bearer ' . $intruderToken,
    ])
        ->assertStatus(403)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'AUTH_UNAUTHORIZED');

    test()->assertDatabaseHas('media_assets', [
        'id' => $assetId,
        'user_id' => $owner->id,
    ]);
});
