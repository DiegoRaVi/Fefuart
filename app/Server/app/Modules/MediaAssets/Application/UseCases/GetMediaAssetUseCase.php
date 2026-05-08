<?php

namespace App\Modules\MediaAssets\Application\UseCases;

use App\Models\MediaAsset;
use App\Modules\MediaAssets\Domain\Contracts\MediaAssetRepository;

final class GetMediaAssetUseCase
{
    public function __construct(private readonly MediaAssetRepository $mediaAssetRepository)
    {
    }

    public function execute(int $id): ?MediaAsset
    {
        return $this->mediaAssetRepository->findById($id);
    }
}
