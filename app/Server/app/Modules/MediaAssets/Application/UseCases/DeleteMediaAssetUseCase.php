<?php

namespace App\Modules\MediaAssets\Application\UseCases;

use App\Models\MediaAsset;
use App\Modules\MediaAssets\Domain\Contracts\MediaAssetRepository;
use Illuminate\Support\Facades\Storage;

final class DeleteMediaAssetUseCase
{
    public function __construct(private readonly MediaAssetRepository $mediaAssetRepository)
    {
    }

    public function execute(MediaAsset $mediaAsset): void
    {
        Storage::disk($mediaAsset->disk)->delete($mediaAsset->path);

        $this->mediaAssetRepository->delete($mediaAsset);
    }
}
