<?php

namespace Database\Factories;

use App\Models\GalleryPiece;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryPiece>
 */
class GalleryPieceFactory extends Factory
{
    protected $model = GalleryPiece::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'media_asset_id' => MediaAsset::factory(),
            'thumbnail_media_id' => MediaAsset::factory(),
            'title' => $this->faker->sentence(3),
            'category' => 'dibujo',
            'description' => null,
            'is_published' => false,
            'sort_order' => 0,
        ];
    }

    public function publicada(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }
}
