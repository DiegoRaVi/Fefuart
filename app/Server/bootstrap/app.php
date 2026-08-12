<?php

use App\Http\Middleware\EnsureIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ARCH-002: v1 escribia aqui `IsUserAuth::class;` e `IsAdmin::class;`
        // — dos sentencias sin efecto. Los middlewares funcionaban solo
        // porque las rutas los referenciaban por clase.

        // D2: Sanctum en modo SPA. Las peticiones que llegan desde un dominio
        // declarado en SANCTUM_STATEFUL_DOMAINS se autentican con la cookie
        // de sesion, no con un token en cabecera. El token deja de estar al
        // alcance de JavaScript, que es lo que convertia un XSS en robo de
        // sesion (SEC-005) y hacia irrevocable el acceso (SEC-011).
        $middleware->statefulApi();

        // SEC-007: v1 no aplicaba ningun limitador. El login admitia fuerza
        // bruta y el registro permitia alta masiva de cuentas. El limitador
        // `api` se define en AppServiceProvider; las rutas sensibles llevan
        // ademas su propio throttle, mas estricto.
        $middleware->api(prepend: [
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
        ]);

        $middleware->alias([
            'admin' => EnsureIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // La API responde siempre JSON. Sin esto, una peticion no autenticada
        // sin cabecera Accept intenta redirigir a la ruta `login`, que no
        // existe en una API, y acaba en un error de rutas en vez de un 401.
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
