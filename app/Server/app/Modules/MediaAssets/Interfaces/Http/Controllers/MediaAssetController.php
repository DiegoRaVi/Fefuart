<?php

namespace App\Modules\MediaAssets\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\MediaAssets\Application\UseCases\DeleteMediaAssetUseCase;
use App\Modules\MediaAssets\Application\UseCases\GetMediaAssetUseCase;
use App\Modules\MediaAssets\Application\UseCases\UploadMediaAssetUseCase;
use App\Modules\MediaAssets\Interfaces\Http\Requests\UploadMediaAssetRequest;
use App\Modules\MediaAssets\Interfaces\Http\Resources\MediaAssetResource;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class MediaAssetController extends Controller
{
    public function __construct(
        private readonly UploadMediaAssetUseCase $uploadMediaAssetUseCase,
        private readonly GetMediaAssetUseCase $getMediaAssetUseCase,
        private readonly DeleteMediaAssetUseCase $deleteMediaAssetUseCase,
    ) {
    }

    public function store(UploadMediaAssetRequest $request): JsonResponse
    {
        $user = $this->resolveApiUser();

        if (!$user) {
            return ApiResponse::error('AUTH_UNAUTHORIZED', 'Unauthorized', 401);
        }

        $asset = $this->uploadMediaAssetUseCase->execute(
            $request->file('file'),
            (int) $user->id,
            $request->validated('context_type'),
            $request->validated('context_id'),
            $request->validated('visibility', 'public'),
        );

        return ApiResponse::success([
            'asset' => (new MediaAssetResource($asset))->resolve(),
        ], 201, ['version' => 'v1']);
    }

    public function show(int $id): JsonResponse
    {
        $asset = $this->getMediaAssetUseCase->execute($id);

        if (!$asset) {
            return ApiResponse::error('RESOURCE_NOT_FOUND', 'Media asset not found', 404);
        }

        if ($asset->visibility === 'private') {
            $user = $this->resolveApiUser();

            if (!$user) {
                return ApiResponse::error('AUTH_UNAUTHORIZED', 'Unauthorized', 401);
            }

            if ((int) $user->id !== (int) $asset->user_id && !in_array($user->role, ['admin', 'assistant'], true)) {
                return ApiResponse::error('AUTH_UNAUTHORIZED', 'Forbidden', 403);
            }
        }

        return ApiResponse::success([
            'asset' => (new MediaAssetResource($asset))->resolve(),
        ], 200, ['version' => 'v1']);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = $this->resolveApiUser();

        if (!$user) {
            return ApiResponse::error('AUTH_UNAUTHORIZED', 'Unauthorized', 401);
        }

        $asset = $this->getMediaAssetUseCase->execute($id);

        if (!$asset) {
            return ApiResponse::error('RESOURCE_NOT_FOUND', 'Media asset not found', 404);
        }

        if ((int) $user->id !== (int) $asset->user_id && !in_array($user->role, ['admin', 'assistant'], true)) {
            return ApiResponse::error('AUTH_UNAUTHORIZED', 'Forbidden', 403);
        }

        $this->deleteMediaAssetUseCase->execute($asset);

        return ApiResponse::success([
            'message' => 'Media asset deleted',
        ], 200, ['version' => 'v1']);
    }

    private function resolveApiUser(): ?User
    {
        $token = request()->bearerToken();

        if (!$token) {
            return null;
        }

        try {
            return JWTAuth::setToken($token)->authenticate();
        } catch (\Throwable) {
            return null;
        }
    }
}
