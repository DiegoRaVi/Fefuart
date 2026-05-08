<?php

namespace App\Modules\MediaAssets\Infrastructure\Persistence;

use App\Models\MediaAsset;
use App\Modules\MediaAssets\Domain\Contracts\MediaAssetRepository;

final class EloquentMediaAssetRepository implements MediaAssetRepository
{
    public function create(array $attributes): MediaAsset
    {
        return MediaAsset::create($attributes);
    }

    public function findById(int $id): ?MediaAsset
    {
        return MediaAsset::find($id);
    }

    public function delete(MediaAsset $mediaAsset): void
    {
        $mediaAsset->delete();
    }
}
