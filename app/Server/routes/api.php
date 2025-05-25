<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsUserAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// PUBLIC ROUTES
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// PRIVATE ROUTES
Route::middleware([IsUserAuth::class])->group(function(){
    Route::controller(AuthController::class)->group(function(){
        Route::post('/logout', 'logout');
        Route::get('/me', 'getUser');
    });

    Route::controller(ProductController::class)->group(function(){
        Route::get('/products', 'getProducts');
        Route::post('/products', 'addProduct');
        Route::delete('/products/{id}', 'deleteProductById');
    });

    Route::controller(EventController::class)->group(function(){
        Route::get('/my-events','getEventsByUser');

        Route::post('/events','addEvent');
        Route::patch('/events/{id}', 'updateEvent');
    });

    Route::controller(OrderController::class)->group(function(){
        Route::get('/order/{id}', 'getOrderById');
        Route::get('/user-orders', 'getOrdersByUserId');
        Route::get('/my-orders', 'getUserOrders');
        Route::post('/cart-order', 'addOrder');
        Route::get('/cart-order', 'getCartOrder');
        Route::patch('/orders/{id}', 'updateOrderById');
    });  
});


// ADMIN ROUTES
Route::middleware([IsAdmin::class])->group(function(){

    Route::controller(ProductController::class)->group(function(){
        Route::get('/products/{orderId}', 'getProductsByOrderId');
        Route::get('/product/{id}', 'getProductById');

        Route::patch('/products/{id}', 'updateProductById');
        Route::delete('/products/{id}', 'deleteProductById');
    });

    Route::controller(EventController::class)->group(function(){
        Route::get('/events/confirmed','getConfirmedEvents');
        Route::get('/events/pending','getPendingEvents');
        Route::get('/events/rejected','getRejectedEvents');
        Route::get('/events/done','getDoneEvents');
        Route::get('/events','getEvents'); 
        Route::get('/events/{id}', 'getEventById');
        
        Route::patch('/events/{id}', 'updateEventById');
        Route::delete('/events/{id}','deleteEventById');
    });

    Route::controller(OrderController::class)->group(function () {
        Route::get('/orders/pending', 'getPendingOrders');
        Route::get('/orders/paid', 'getPaidOrders');
        Route::get('/orders/shipped', 'getShippedOrders');
        Route::get('/orders/cancelled', 'getCancelledOrders');
        Route::get('/orders/{id}','getOrdersByUserId');

        Route::patch('/orders/{id}', 'updateOrderById');
        Route::delete('/orders/{id}', 'deleteOrderById');
    });
});