<?php

use App\Http\Controllers\Api\Admin\EventController as AdminEventController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\ProductVariantController as AdminVariantController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\StripeWebhookController;
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
    Route::post('checkout', [CartController::class, 'checkout'])->name('cart.checkout');
});

// Pedidos. Las transiciones de estado van por sub-recurso, nunca por un
// `PATCH {status}` como en v1 (SEC-003): no hay ningun endpoint que acepte
// el estado o el total en el cuerpo.
Route::middleware('auth:sanctum')->prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index'])->name('orders.index');
    Route::get('{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    // D3 — abre la sesion de pago y devuelve la URL de Stripe. El cuerpo va
    // vacio: ni importe ni moneda ni metodo llegan del cliente (SEC-006).
    // Que el pedido quede pagado no lo decide esta ruta, sino el webhook.
    Route::post('{order}/pay', [PaymentController::class, 'store'])->name('orders.pay');
});

// D3, D7 — la unica ruta de la API sin sesion. No la protege `auth:sanctum`
// —Stripe no tiene cuenta aqui— sino la firma del cuerpo, que se verifica
// sobre los bytes tal cual llegaron. Es tambien el unico sitio del sistema
// donde un pedido pasa a `paid`.
Route::post('webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');

// D5: el catalogo se gestiona desde el backoffice. `admin` va detras de
// `auth:sanctum` para que un invitado reciba 401 y un cliente 403; el
// `IsAdmin` de v1 devolvia 403 en los dos casos (ARCH-002).
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Por id y no por slug: el catalogo publico resuelve por slug porque es
    // lo legible en una URL, pero el slug es editable desde aqui y usarlo
    // como identificador en el backoffice significaria que renombrar un
    // producto cambia su direccion.
    Route::get('products', [AdminProductController::class, 'index'])->name('products.index');
    Route::post('products', [AdminProductController::class, 'store'])->name('products.store');
    Route::get('products/{product:id}', [AdminProductController::class, 'show'])->name('products.show');
    Route::patch('products/{product:id}', [AdminProductController::class, 'update'])->name('products.update');
    Route::delete('products/{product:id}', [AdminProductController::class, 'destroy'])->name('products.destroy');

    Route::post('products/{product:id}/variants', [AdminVariantController::class, 'store'])
        ->name('products.variants.store');
    Route::patch('variants/{variant}', [AdminVariantController::class, 'update'])
        ->name('variants.update');
    Route::delete('variants/{variant}', [AdminVariantController::class, 'destroy'])
        ->name('variants.destroy');

    // Pedidos: lo que v1 hacia en admin.html, ahora con paginacion, filtros
    // validados y sin la peticion por fila de PERF-001.
    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])
        ->name('orders.status');

    // Eventos: rechazar, cancelar y completar. Presupuestar y confirmar
    // necesitan importe y señal, y llegan con la Fase 5 (D27).
    Route::get('events', [AdminEventController::class, 'index'])->name('events.index');
    Route::get('events/{event}', [AdminEventController::class, 'show'])->name('events.show');
    Route::post('events/{event}/status', [AdminEventController::class, 'updateStatus'])
        ->name('events.status');
});

// Solicitudes de Live Art. Ninguna ruta acepta `status` en el cuerpo: eso
// era SEC-010, y se activaba justo al arreglar BUG-002 (el PATCH que en v1
// apuntaba a un metodo inexistente). Presupuestar y confirmar son del
// backoffice.
Route::middleware('auth:sanctum')->prefix('events')->group(function () {
    Route::get('/', [EventController::class, 'index'])->name('events.index');
    Route::post('/', [EventController::class, 'store'])->name('events.store');
    Route::get('{event}', [EventController::class, 'show'])->name('events.show');
    Route::patch('{event}', [EventController::class, 'update'])->name('events.update');
    Route::post('{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
});

// N19: perfil. Todo requiere sesion; nada aqui permite tocar el rol.
Route::middleware('auth:sanctum')->prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});
