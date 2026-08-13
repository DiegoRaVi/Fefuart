<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configurePasswordResetUrl();
    }

    /**
     * El enlace de recuperacion tiene que llevar a la SPA, no a una ruta del
     * backend: es React quien muestra el formulario y luego llama a la API.
     */
    private function configurePasswordResetUrl(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return sprintf(
                '%s/restablecer-contrasena?token=%s&email=%s',
                rtrim((string) config('app.frontend_url'), '/'),
                $token,
                urlencode($notifiable->getEmailForPasswordReset())
            );
        });
    }

    /**
     * SEC-007: limitador general de la API. Cuenta por usuario cuando hay
     * sesion y por IP cuando no la hay, para que un invitado no consuma la
     * cuota de otro tras una NAT compartida.
     *
     * Los endpoints de autenticacion llevan encima un throttle propio y mas
     * estricto, declarado en las rutas.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
