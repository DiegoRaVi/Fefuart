<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmailVerificationController;
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

// N19: perfil. Todo requiere sesion; nada aqui permite tocar el rol.
Route::middleware('auth:sanctum')->prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});
