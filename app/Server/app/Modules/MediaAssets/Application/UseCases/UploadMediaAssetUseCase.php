<?php

namespace App\Modules\MediaAssets\Application\UseCases;

use App\Models\MediaAsset;
use App\Modules\MediaAssets\Domain\Contracts\MediaAssetRepository;
use Illuminate\Http\UploadedFile;

final class UploadMediaAssetUseCase
{
    public function __construct(private readonly MediaAssetRepository $mediaAssetRepository)
    {
    }

    public function execute(
        UploadedFile $file,
        int $userId,
        ?string $contextType,
        ?int $contextId,
        string $visibility = 'public'
    ): MediaAsset {
        $path = $file->store('media-assets/' . now()->format('Y/m'), [
            'disk' => 'public',
            'visibility' => $visibility,
        ]);

        return $this->mediaAssetRepository->create([
            'user_id' => $userId,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?? 'application/octet-stream',
            'extension' => $file->getClientOriginalExtension(),
            'size_bytes' => $file->getSize() ?? 0,
            'visibility' => $visibility,
        ]);
    }
}
