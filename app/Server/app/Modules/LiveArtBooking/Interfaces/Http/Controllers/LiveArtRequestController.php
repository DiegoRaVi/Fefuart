<?php

namespace App\Modules\LiveArtBooking\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\LiveArtBooking\Application\DTOs\CreateLiveArtRequestData;
use App\Modules\LiveArtBooking\Application\UseCases\CreateLiveArtRequestUseCase;
use App\Modules\LiveArtBooking\Interfaces\Http\Requests\CreateLiveArtRequestRequest;
use App\Modules\LiveArtBooking\Interfaces\Http\Resources\LiveArtRequestResource;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LiveArtRequestController extends Controller
{
    public function __construct(private readonly CreateLiveArtRequestUseCase $createLiveArtRequestUseCase)
    {
    }

    public function store(CreateLiveArtRequestRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponse::error('AUTH_UNAUTHORIZED', 'Unauthorized', 401);
        }

        $dto = CreateLiveArtRequestData::fromArray($request->validated());
        $liveArtRequest = $this->createLiveArtRequestUseCase->execute($dto, (int) $user->id);

        return ApiResponse::success(
            (new LiveArtRequestResource($liveArtRequest))->resolve($request),
            201,
            ['version' => 'v1']
        );
    }
}
