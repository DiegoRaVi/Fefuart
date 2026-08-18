<?php

namespace App\Providers;

use App\Services\StripePaymentService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerStripe();
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configurePasswordResetUrl();
    }

    /**
     * D7 — el cliente de Stripe se inyecta, no se configura en estatico.
     *
     * `Stripe::setApiKey()` esta obsoleto en el SDK y ademas deja la clave en
     * estado global, que es justo lo que impide sustituirla en un test. Con
     * una instancia en el contenedor, los tests atan un doble y ninguna
     * peticion sale de la maquina.
     *
     * La version de la API se fija para que un cambio del valor por defecto
     * de Stripe no altere los campos que lee el webhook.
     */
    private function registerStripe(): void
    {
        $this->app->singleton(StripeClient::class, fn () => new StripeClient([
            'api_key' => (string) config('services.stripe.secret'),
            'stripe_version' => StripePaymentService::VERSION_API,
        ]));
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
            // El webhook de la pasarela no cabe en el limite de un cliente:
            // Stripe entrega en rafagas y reintenta lo que le devolvemos como
            // 429, de modo que estrangularlo solo consigue mas trafico. Se le
            // deja un limite propio y amplio, por IP, que sigue frenando a
            // quien intente inundar la ruta.
            if ($request->is('api/webhooks/*')) {
                return Limit::perMinute(300)->by($request->ip());
            }

            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
