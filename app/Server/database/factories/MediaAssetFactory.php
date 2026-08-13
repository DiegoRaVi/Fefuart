<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::random(40).'.jpg';

        return [
            'user_id' => User::factory(),
            'path' => 'referencias/'.$name,
            'original_name' => 'foto-de-la-boda.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 245_000,
            'visibility' => 'public',
        ];
    }

    public function private(): static
    {
        return $this->state(fn () => [
            'path' => 'entregas/'.Str::random(40).'.jpg',
            'visibility' => 'private',
        ]);
    }
}
