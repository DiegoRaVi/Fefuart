<?php

namespace App\Providers;

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
