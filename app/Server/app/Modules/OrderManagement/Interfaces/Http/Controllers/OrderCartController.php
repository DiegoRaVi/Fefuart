<?php

namespace App\Modules\OrderManagement\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OrderManagement\Application\DTOs\AddCartItemData;
use App\Modules\OrderManagement\Application\UseCases\AddCatalogItemToCartUseCase;
use App\Modules\OrderManagement\Application\UseCases\AddItemToCartUseCase;
use App\Modules\OrderManagement\Application\UseCases\CheckoutCartUseCase;
use App\Modules\OrderManagement\Application\UseCases\CreateOrGetCartUseCase;
use App\Modules\OrderManagement\Application\UseCases\GetCurrentCartUseCase;
use App\Modules\OrderManagement\Application\UseCases\GetUserOrdersUseCase;
use App\Modules\OrderManagement\Application\UseCases\RemoveCartItemUseCase;
use App\Modules\OrderManagement\Application\UseCases\UpdateCartItemQuantityUseCase;
use App\Modules\OrderManagement\Interfaces\Http\Requests\AddCatalogItemRequest;
use App\Modules\OrderManagement\Interfaces\Http\Requests\AddCartItemRequest;
use App\Modules\OrderManagement\Interfaces\Http\Requests\CheckoutCartRequest;
use App\Modules\OrderManagement\Interfaces\Http\Requests\UpdateCartItemRequest;
use App\Modules\OrderManagement\Interfaces\Http\Resources\CartItemResource;
use App\Modules\OrderManagement\Interfaces\Http\Resources\CartResource;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class OrderCartController extends Controller
{
    public function __construct(
        private readonly CreateOrGetCartUseCase $createOrGetCartUseCase,
        private readonly GetCurrentCartUseCase $getCurrentCartUseCase,
        private readonly AddItemToCartUseCase $addItemToCartUseCase,
        private readonly AddCatalogItemToCartUseCase $addCatalogItemToCartUseCase,
        private readonly UpdateCartItemQuantityUseCase $updateCartItemQuantityUseCase,
        private readonly RemoveCartItemUseCase $removeCartItemUseCase,
        private readonly CheckoutCartUseCase $checkoutCartUseCase,
        private readonly GetUserOrdersUseCase $getUserOrdersUseCase,
    ) {
    }

    public function show(): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return ApiResponse::error('AUTH_UNAUTHORIZED', 'Unauthorized', 401);
        }

        $cart = $this->getCurrentCartUseCase->execute((int) $user->id);

        if (!$cart) {
            return ApiResponse::error('RESOURCE_NOT_FOUND', 'Cart not found', 404);
        }

        return ApiResponse::success([
            'cart' => (new CartResource($cart))->resolve(),
        ], 200, ['version' => 'v1']);
    }

    public function store(): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return ApiResponse::error('AUTH_UNAUTHORIZED', 'Unauthorized', 401);
        }

        $cart = $this->createOrGetCartUseCase->execute((int) $user->id);

        return ApiResponse::success([
            'cart' => (new CartResource($cart))->resolve(),
        ], 200, ['version' => 'v1']);
    }

    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return ApiResponse::error('AUTH_UNAUTHORIZED', 'Unauthorized', 401);
        }

        $dto = AddCartItemData::fromArray($request->validated());
        $result = $this->addItemToCartUseCase->execute((int) $user->id, $dto);

        return ApiResponse::success([
            'cart' => (new CartResource($result['cart']))->resolve(),
            'item' => (new CartItemResource($result['item']))->resolve(),
        ], 201, ['version' => 'v1']);
    }

    public function addCatalogItem(AddCatalogItemRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return ApiResponse::error('AUTH_UNAUTHORIZED', 'Unauthorized', 401);
        }

        try {
            $result = $this->addCatalogItemToCartUseCase->execute(
                (int) $user->id,
                (int) $request->validated('product_id'),
                (int) $request->validated('quantity')
            );
        } catch (RuntimeException $exception) {
            return ApiResponse::error('RESOURCE_NOT_FOUND', $exception->getMessage(), 404);
        }

        return ApiResponse::success([
            'cart' => (new CartResource($result['cart']))->resolve(),
            'item' => (new CartItemResource($result['item']))->resolve(),
        ], 201, ['version' => 'v1']);
    }

    public function updateItem(UpdateCartItemRequest $request, int $id): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return ApiResponse::error('AUTH_UNAUTHORIZED', 'Unauthorized', 401);
        }

        try {
            $result = $this->updateCartItemQuantityUseCase->execute(
                (int) $user->id,
                $id,
                (int) $request->validated('quantity')
            );
        } catch (RuntimeException $exception) {
            return ApiResponse::error('RESOURCE_NOT_FOUND', $exception->getMessage(), 404);
        }

        return ApiResponse::success([
            'cart' => (new CartResource($result['cart']))->resolve(),
            'item' => (new CartItemResource($result['item']))->resolve(),
        ], 200, ['version' => 'v1']);
    }

    public function removeItem(int $id): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return ApiResponse::error('AUTH_UNAUTHORIZED', 'Unauthorized', 401);
        }

        try {
            $cart = $this->removeCartItemUseCase->execute((int) $user->id, $id);
        } catch (RuntimeException $exception) {
            return ApiResponse::error('RESOURCE_NOT_FOUND', $exception->getMessage(), 404);
        }

        return ApiResponse::success([
            'cart' => (new CartResource($cart))->resolve(),
        ], 200, ['version' => 'v1']);
    }

    public function checkout(CheckoutCartRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return ApiResponse::error('AUTH_UNAUTHORIZED', 'Unauthorized', 401);
        }

        try {
            $order = $this->checkoutCartUseCase->execute((int) $user->id, $request->validated('address'));
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
            if ($message === 'Cart not found') {
                return ApiResponse::error('RESOURCE_NOT_FOUND', $message, 404);
            }

            return ApiResponse::error('BUSINESS_RULE_VIOLATION', $message, 422);
        }

        return ApiResponse::success([
            'order' => (new CartResource($order))->resolve(),
        ], 200, ['version' => 'v1']);
    }

    public function myOrders(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return ApiResponse::error('AUTH_UNAUTHORIZED', 'Unauthorized', 401);
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['all', 'pending', 'paid', 'shipped', 'cancelled', 'rejected', 'done'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $status = $validated['status'] ?? null;
        if ($status === 'all') {
            $status = null;
        }

        $perPage = (int) ($validated['per_page'] ?? 10);
        $orders = $this->getUserOrdersUseCase->execute((int) $user->id, $perPage, $status);

        return ApiResponse::success([
            'orders' => CartResource::collection($orders->items())->resolve(),
        ], 200, [
            'version' => 'v1',
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
            'filters' => [
                'status' => $status ?? 'all',
            ],
        ]);
    }
}
