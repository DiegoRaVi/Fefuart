<?php

use App\Http\Controllers\Api\AuthController;
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

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
    });
});
