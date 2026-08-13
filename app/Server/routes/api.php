<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API
|--------------------------------------------------------------------------
|
| Sin prefijo de version (D13): v2 es la primera version funcional y
| desplegada oficialmente.
|
| Las 40 rutas de v1 se retiraron al migrar a Sanctum. Dependian del guard
| `api` de JWT, arrastraban cinco fallos criticos de autorizacion y tres
| apuntaban a metodos inexistentes. No se mantienen en paralelo (D15).
|
| Antes de la primera peticion autenticada, la SPA pide la cookie CSRF a
| GET /sanctum/csrf-cookie, que registra el propio paquete.
|
*/

Route::prefix('auth')->group(function () {
    // SEC-007: throttle propio, mas estricto que el limitador general de la
    // API. En login se suma el limitador por email+IP de LoginRequest.
    //
    // Nota conocida: `unique:users` en el registro sigue revelando que
    // direcciones existen. Es inherente al alta con email y se mitiga con
    // este limite; la alternativa (aceptar siempre y avisar por correo)
    // se valorara si el volumen lo justifica.
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:6,1')
        ->name('auth.register');

    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.login');

    // N19: recuperacion de contrasena. Limitada con mano dura — es un
    // endpoint publico que dispara envio de correo.
    Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::post('reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:5,1')
        ->name('password.store');

    // Destino del enlace firmado del correo de verificacion. No lleva
    // auth:sanctum: quien pincha desde el gestor de correo puede no traer
    // cookie de sesion. La firma de la URL es lo que autoriza.
    Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');

        Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
    });
});

// Catalogo publico: cualquiera puede ver que se vende. Encargar si exige
// cuenta (N18). Es de donde el cliente **lee** el precio; el carrito no lo
// acepta de vuelta (SEC-006).
Route::prefix('catalog')->group(function () {
    Route::get('products', [CatalogController::class, 'index'])->name('catalog.products.index');
    Route::get('products/{product}', [CatalogController::class, 'show'])->name('catalog.products.show');
});

// N9: la foto de partida se sube antes que el encargo y devuelve un id.
// El borrado pasa por MediaAssetPolicy (SEC-004).
Route::middleware('auth:sanctum')->group(function () {
    Route::post('media', [MediaController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('media.store');

    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
});

// SEC-006: el cuerpo dice *que* se encarga, nunca cuanto cuesta. Cada
// respuesta devuelve el pedido entero recalculado en servidor.
Route::middleware('auth:sanctum')->prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'show'])->name('cart.show');
    Route::post('items', [CartController::class, 'store'])->name('cart.items.store');
    Route::patch('items/{item}', [CartController::class, 'update'])->name('cart.items.update');
    Route::delete('items/{item}', [CartController::class, 'destroy'])->name('cart.items.destroy');
});

// Pedidos. Las transiciones de estado van por sub-recurso, nunca por un
// `PATCH {status}` como en v1 (SEC-003): no hay ningun endpoint que acepte
// el estado o el total en el cuerpo.
Route::middleware('auth:sanctum')->prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index'])->name('orders.index');
    Route::get('{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
});

// N19: perfil. Todo requiere sesion; nada aqui permite tocar el rol.
Route::middleware('auth:sanctum')->prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});
