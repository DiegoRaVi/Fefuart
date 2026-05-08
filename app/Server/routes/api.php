<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsBackoffice;
use App\Http\Middleware\IsUserAuth;
use App\Modules\BackofficeOps\Interfaces\Http\Controllers\BackofficeOpsController;
use App\Modules\Catalog\Interfaces\Http\Controllers\CatalogProductController;
use App\Modules\IdentityAccess\Interfaces\Http\Controllers\IdentityAuthController;
use App\Modules\LiveArtBooking\Interfaces\Http\Controllers\LiveArtRequestController;
use App\Modules\MediaAssets\Interfaces\Http\Controllers\MediaAssetController;
use App\Modules\Notifications\Interfaces\Http\Controllers\NotificationController;
use App\Modules\OrderManagement\Interfaces\Http\Controllers\OrderCartController;
use App\Shared\Http\ApiResponse;
use Illuminate\Support\Facades\Route;

// V1 ROUTES (REBUILD)
Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return ApiResponse::success([
            'service' => 'fefuart-api',
            'status' => 'ok',
        ], 200, ['version' => 'v1']);
    });

    Route::post('/auth/register', [IdentityAuthController::class, 'register']);
    Route::post('/auth/login', [IdentityAuthController::class, 'login']);
    Route::get('/catalog/products', [CatalogProductController::class, 'index']);
    Route::get('/catalog/products/{id}', [CatalogProductController::class, 'show']);
    Route::get('/media/{id}', [MediaAssetController::class, 'show']);

    Route::middleware([IsUserAuth::class])->group(function () {
        Route::get('/auth/me', [IdentityAuthController::class, 'me']);
        Route::post('/auth/logout', [IdentityAuthController::class, 'logout']);
        Route::post('/live-art/requests', [LiveArtRequestController::class, 'store']);
        Route::post('/media/upload', [MediaAssetController::class, 'store']);
        Route::delete('/media/{id}', [MediaAssetController::class, 'destroy']);
        Route::post('/cart', [OrderCartController::class, 'store']);
        Route::get('/cart', [OrderCartController::class, 'show']);
        Route::post('/cart/items', [OrderCartController::class, 'addItem']);
        Route::post('/cart/items/from-catalog', [OrderCartController::class, 'addCatalogItem']);
        Route::patch('/cart/items/{id}', [OrderCartController::class, 'updateItem']);
        Route::delete('/cart/items/{id}', [OrderCartController::class, 'removeItem']);
        Route::post('/cart/checkout', [OrderCartController::class, 'checkout']);
        Route::get('/orders/my', [OrderCartController::class, 'myOrders']);
        Route::get('/notifications/my', [NotificationController::class, 'my']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    });

    Route::middleware([IsUserAuth::class, IsBackoffice::class])->group(function () {
        Route::post('/catalog/products', [CatalogProductController::class, 'store']);
        Route::patch('/catalog/products/{id}', [CatalogProductController::class, 'update']);

        Route::get('/backoffice/summary', [BackofficeOpsController::class, 'summary']);
        Route::get('/backoffice/orders', [BackofficeOpsController::class, 'listOrders']);
        Route::patch('/backoffice/orders/{id}/status', [BackofficeOpsController::class, 'updateOrderStatus']);
        Route::get('/backoffice/events', [BackofficeOpsController::class, 'listEvents']);
        Route::patch('/backoffice/events/{id}/status', [BackofficeOpsController::class, 'updateEventStatus']);
    });
});

// PUBLIC ROUTES
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// PRIVATE ROUTES
Route::middleware([IsUserAuth::class])->group(function(){
    Route::controller(AuthController::class)->group(function(){
        Route::post('/logout', 'logout');
        Route::get('/me', 'getUser');
        Route::get('/user/{id}', 'getUserById');
    });

    Route::controller(ProductController::class)->group(function(){
        Route::get('/products', 'getProducts');
        Route::get('/products/{orderId}', 'getProductsByOrderId');
        Route::post('/products', 'addProduct');
        Route::delete('/products/{id}', 'deleteProductById');
    });

    Route::controller(EventController::class)->group(function(){
        Route::get('/my-events','getEventsByUser');

        Route::post('/events','addEvent');
        Route::patch('/events/{id}', 'updateEventById');
    });

    Route::controller(OrderController::class)->group(function(){
        Route::get('/order/{id}', 'getOrderById');
        Route::get('/user-orders', 'getUserOrders');
        Route::get('/my-orders', 'getUserOrders');
        Route::post('/cart-order', 'addOrder');
        Route::get('/cart-order', 'getCartOrder');
        Route::patch('/orders/{id}', 'updateOrderById');
    });  
});


// ADMIN ROUTES
Route::middleware([IsAdmin::class])->group(function(){

    Route::controller(ProductController::class)->group(function(){

        Route::get('/product/{id}', 'getProductById');

        Route::patch('/products/{id}', 'updateProductById');
        //Route::delete('/products/{id}', 'deleteProductById');
    });

    Route::controller(EventController::class)->group(function(){
        Route::get('/events/confirmed','getConfirmedEvents');
        Route::get('/events/pending','getPendingEvents');
        Route::get('/events/rejected','getRejectedEvents');
        Route::get('/events/done','getDoneEvents');
        Route::get('/events','getEvents'); 
        Route::get('/events/{id}', 'getEventById');
        
        Route::patch('/admin/events/{id}', 'updateEventById');
        Route::delete('/events/{id}','deleteEventById');
    });

    Route::controller(OrderController::class)->group(function () {
        Route::get('/orders/pending', 'getPendingOrders');
        Route::get('/orders/paid', 'getPaidOrders');
        Route::get('/orders/shipped', 'getShippedOrders');
        Route::get('/orders/cancelled', 'getCancelledOrders');
        Route::get('/orders/search', 'getOrdersByUserEmail');
        Route::get('/orders/{id}','getOrdersByUserId');
        Route::patch('/admin/orders/{id}', 'updateOrderById');
        Route::delete('/orders/{id}', 'deleteOrderById');
    });
});