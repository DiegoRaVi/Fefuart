<?php

namespace App\Modules\IdentityAccess\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Application\DTOs\LoginData;
use App\Modules\IdentityAccess\Application\DTOs\RegisterUserData;
use App\Modules\IdentityAccess\Application\UseCases\LoginUserUseCase;
use App\Modules\IdentityAccess\Application\UseCases\LogoutCurrentUserUseCase;
use App\Modules\IdentityAccess\Application\UseCases\RegisterUserUseCase;
use App\Modules\IdentityAccess\Domain\Contracts\UserIdentityRepository;
use App\Modules\IdentityAccess\Interfaces\Http\Requests\LoginRequest;
use App\Modules\IdentityAccess\Interfaces\Http\Requests\RegisterRequest;
use App\Modules\IdentityAccess\Interfaces\Http\Resources\IdentityUserResource;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class IdentityAuthController extends Controller
{
    public function __construct(
        private readonly RegisterUserUseCase $registerUserUseCase,
        private readonly LoginUserUseCase $loginUserUseCase,
        private readonly LogoutCurrentUserUseCase $logoutCurrentUserUseCase,
        private readonly UserIdentityRepository $userIdentityRepository,
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $dto = RegisterUserData::fromArray($request->validated());
        $user = $this->registerUserUseCase->execute($dto);

        return ApiResponse::success([
            'user' => (new IdentityUserResource($user))->resolve(),
        ], 201, ['version' => 'v1']);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $dto = LoginData::fromArray($request->validated());
        $token = $this->loginUserUseCase->execute($dto);

        if (!$token) {
            return ApiResponse::error('AUTH_INVALID_CREDENTIALS', 'Invalid credentials', 401);
        }

        $user = $this->userIdentityRepository->findByEmail($dto->email);

        return ApiResponse::success([
            'token' => $token,
            'user' => $user ? (new IdentityUserResource($user))->resolve() : null,
        ], 200, ['version' => 'v1']);
    }

    public function me(): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return ApiResponse::error('AUTH_UNAUTHORIZED', 'Unauthorized', 401);
        }

        return ApiResponse::success([
            'user' => (new IdentityUserResource($user))->resolve(),
        ], 200, ['version' => 'v1']);
    }

    public function logout(): JsonResponse
    {
        $this->logoutCurrentUserUseCase->execute();

        return ApiResponse::success([
            'message' => 'Logged out successfully',
        ], 200, ['version' => 'v1']);
    }
}
