<?php

namespace App\Modules\Notifications\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Application\UseCases\ListUserNotificationsUseCase;
use App\Modules\Notifications\Application\UseCases\MarkNotificationAsReadUseCase;
use App\Modules\Notifications\Interfaces\Http\Requests\ListMyNotificationsRequest;
use App\Modules\Notifications\Interfaces\Http\Resources\OperationalNotificationResource;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(
        private readonly ListUserNotificationsUseCase $listUserNotificationsUseCase,
        private readonly MarkNotificationAsReadUseCase $markNotificationAsReadUseCase,
    ) {
    }

    public function my(ListMyNotificationsRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return ApiResponse::error('AUTH_UNAUTHORIZED', 'Unauthorized', 401);
        }

        $perPage = (int) $request->validated('per_page', 15);
        $notifications = $this->listUserNotificationsUseCase->execute((int) $user->id, $perPage);

        return ApiResponse::success([
            'notifications' => OperationalNotificationResource::collection($notifications->items())->resolve(),
        ], 200, [
            'version' => 'v1',
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function markRead(int $id): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return ApiResponse::error('AUTH_UNAUTHORIZED', 'Unauthorized', 401);
        }

        $notification = $this->markNotificationAsReadUseCase->execute($id, (int) $user->id);

        if (!$notification) {
            return ApiResponse::error('RESOURCE_NOT_FOUND', 'Notification not found', 404);
        }

        return ApiResponse::success([
            'notification' => (new OperationalNotificationResource($notification))->resolve(),
        ], 200, ['version' => 'v1']);
    }
}
