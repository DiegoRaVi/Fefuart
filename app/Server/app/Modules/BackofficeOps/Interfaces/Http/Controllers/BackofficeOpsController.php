<?php

namespace App\Modules\BackofficeOps\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BackofficeOps\Application\UseCases\GetBackofficeSummaryUseCase;
use App\Modules\BackofficeOps\Application\UseCases\ListBackofficeEventsUseCase;
use App\Modules\BackofficeOps\Application\UseCases\ListBackofficeOrdersUseCase;
use App\Modules\BackofficeOps\Application\UseCases\UpdateBackofficeEventStatusUseCase;
use App\Modules\BackofficeOps\Application\UseCases\UpdateBackofficeOrderStatusUseCase;
use App\Modules\BackofficeOps\Domain\Contracts\BackofficeEventRepository;
use App\Modules\BackofficeOps\Domain\Contracts\BackofficeOrderRepository;
use App\Modules\BackofficeOps\Interfaces\Http\Requests\ListBackofficeEventsRequest;
use App\Modules\BackofficeOps\Interfaces\Http\Requests\ListBackofficeOrdersRequest;
use App\Modules\BackofficeOps\Interfaces\Http\Requests\UpdateBackofficeEventStatusRequest;
use App\Modules\BackofficeOps\Interfaces\Http\Requests\UpdateBackofficeOrderStatusRequest;
use App\Modules\BackofficeOps\Interfaces\Http\Resources\BackofficeEventResource;
use App\Modules\BackofficeOps\Interfaces\Http\Resources\BackofficeOrderResource;
use App\Modules\Notifications\Application\UseCases\CreateOperationalNotificationUseCase;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class BackofficeOpsController extends Controller
{
    public function __construct(
        private readonly ListBackofficeOrdersUseCase $listBackofficeOrdersUseCase,
        private readonly UpdateBackofficeOrderStatusUseCase $updateBackofficeOrderStatusUseCase,
        private readonly ListBackofficeEventsUseCase $listBackofficeEventsUseCase,
        private readonly UpdateBackofficeEventStatusUseCase $updateBackofficeEventStatusUseCase,
        private readonly GetBackofficeSummaryUseCase $getBackofficeSummaryUseCase,
        private readonly BackofficeOrderRepository $backofficeOrderRepository,
        private readonly BackofficeEventRepository $backofficeEventRepository,
        private readonly CreateOperationalNotificationUseCase $createOperationalNotificationUseCase,
    ) {
    }

    public function summary(): JsonResponse
    {
        return ApiResponse::success([
            'summary' => $this->getBackofficeSummaryUseCase->execute(),
        ], 200, ['version' => 'v1']);
    }

    public function listOrders(ListBackofficeOrdersRequest $request): JsonResponse
    {
        $status = $request->validated('status');
        $perPage = (int) $request->validated('per_page', 20);

        $orders = $this->listBackofficeOrdersUseCase->execute($status, $perPage);

        return ApiResponse::success([
            'orders' => BackofficeOrderResource::collection($orders->items())->resolve(),
        ], 200, [
            'version' => 'v1',
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function updateOrderStatus(UpdateBackofficeOrderStatusRequest $request, int $id): JsonResponse
    {
        $order = $this->backofficeOrderRepository->findById($id);

        if (!$order) {
            return ApiResponse::error('RESOURCE_NOT_FOUND', 'Order not found', 404);
        }

        $previousStatus = $order->status;
        $updatedOrder = $this->updateBackofficeOrderStatusUseCase->execute($order, $request->validated('status'));

        if ($previousStatus !== $updatedOrder->status) {
            $this->createOperationalNotificationUseCase->execute([
                'user_id' => (int) $updatedOrder->user_id,
                'actor_user_id' => optional(auth('api')->user())->id,
                'context_type' => 'order',
                'context_id' => (int) $updatedOrder->id,
                'channel' => 'in_app',
                'title' => 'Estado de pedido actualizado',
                'body' => sprintf(
                    'Tu pedido #%d cambió de %s a %s.',
                    (int) $updatedOrder->id,
                    $previousStatus,
                    $updatedOrder->status
                ),
                'previous_status' => $previousStatus,
                'new_status' => $updatedOrder->status,
                'payload' => [
                    'order_id' => (int) $updatedOrder->id,
                ],
            ]);
        }

        return ApiResponse::success([
            'order' => (new BackofficeOrderResource($updatedOrder))->resolve(),
        ], 200, ['version' => 'v1']);
    }

    public function listEvents(ListBackofficeEventsRequest $request): JsonResponse
    {
        $status = $request->validated('status');
        $perPage = (int) $request->validated('per_page', 20);

        $events = $this->listBackofficeEventsUseCase->execute($status, $perPage);

        return ApiResponse::success([
            'events' => BackofficeEventResource::collection($events->items())->resolve(),
        ], 200, [
            'version' => 'v1',
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    public function updateEventStatus(UpdateBackofficeEventStatusRequest $request, int $id): JsonResponse
    {
        $event = $this->backofficeEventRepository->findById($id);

        if (!$event) {
            return ApiResponse::error('RESOURCE_NOT_FOUND', 'Event not found', 404);
        }

        $previousStatus = $event->status;
        $updatedEvent = $this->updateBackofficeEventStatusUseCase->execute($event, $request->validated('status'));

        if ($previousStatus !== $updatedEvent->status) {
            $this->createOperationalNotificationUseCase->execute([
                'user_id' => (int) $updatedEvent->user_id,
                'actor_user_id' => optional(auth('api')->user())->id,
                'context_type' => 'event',
                'context_id' => (int) $updatedEvent->id,
                'channel' => 'in_app',
                'title' => 'Estado de solicitud actualizado',
                'body' => sprintf(
                    'Tu solicitud de live art #%d cambió de %s a %s.',
                    (int) $updatedEvent->id,
                    $previousStatus,
                    $updatedEvent->status
                ),
                'previous_status' => $previousStatus,
                'new_status' => $updatedEvent->status,
                'payload' => [
                    'event_id' => (int) $updatedEvent->id,
                ],
            ]);
        }

        return ApiResponse::success([
            'event' => (new BackofficeEventResource($updatedEvent))->resolve(),
        ], 200, ['version' => 'v1']);
    }
}
