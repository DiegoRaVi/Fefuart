<?php

namespace App\Modules\MediaAssets\Domain\Contracts;

use App\Models\MediaAsset;

interface MediaAssetRepository
{
    public function create(array $attributes): MediaAsset;

    public function findById(int $id): ?MediaAsset;

    public function delete(MediaAsset $mediaAsset): void;
}
