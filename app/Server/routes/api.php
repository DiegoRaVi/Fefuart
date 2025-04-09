<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsUserAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//PUBLIC ROUTES
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

//PRIVATE ROUTES
Route::middleware([IsUserAuth::class])->group(function(){
    Route::controller(AuthController::class)->group(function(){
        Route::post('/logout', 'logout');
        Route::get('/me', 'getUser');
    });

    Route::controller(ProductController::class)->group(function(){
        Route::get('/products', 'getProducts');
    });

    Route::controller(EventController::class)->group(function(){
        Route::get('/my-events','getEventsByUserId');
        Route::get('/events/{id}', 'getEventById');
        Route::post('/events','addEvent');
        Route::patch('/events/{id}', 'updateEvent');
    });

    Route::controller(OrderController::class)->group(function () {
        Route::get('/orders/{id}', 'getOrderById');
        Route::get('/my-orders', 'getOrdersByUser');
        Route::post('/orders', 'addOrder');
    });
    
});


//ADMIN ROUTES
Route::middleware([IsAdmin::class])->group(function(){

    Route::controller(ProductController::class)->group(function(){
        Route::post('/products', 'addProduct');
        Route::get('/products/{id}', 'getProductById');
        Route::patch('/products/{id}', 'updateProductById');
        Route::delete('/products/{id}', 'deleteProductById');
    });

    Route::controller(EventController::class)->group(function(){
        Route::get('/events','listEvents');
        Route::delete('/events/{id}','deleteEvent');
    });

    Route::controller(OrderController::class)->group(function () {
        Route::get('/orders', 'getOrders');
        Route::patch('/orders/{id}', 'updateOrderById');
        Route::delete('/orders/{id}', 'deleteOrderById');
    });
});