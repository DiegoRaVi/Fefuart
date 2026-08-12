<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));
    RateLimiter::clear('correcta@fefuart.test|127.0.0.1');
});

it('autentica con credenciales validas', function () {
    $user = User::factory()->create(['email' => 'correcta@fefuart.test']);

    $this->postJson(route('auth.login'), [
        'email' => 'correcta@fefuart.test',
        'password' => 'password',
    ])->assertOk()->assertJsonPath('data.id', $user->id);

    $this->assertAuthenticatedAs($user);
});

it('rechaza credenciales invalidas sin abrir sesion', function () {
    User::factory()->create(['email' => 'correcta@fefuart.test']);

    $this->postJson(route('auth.login'), [
        'email' => 'correcta@fefuart.test',
        'password' => 'no-es-esta',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    $this->assertGuest();
});

/**
 * SEC-007 — regresion.
 *
 * v1 no aplicaba ningun limitador: `php artisan route:list` mostraba las
 * rutas de login y registro sin middleware `throttle`, asi que la fuerza
 * bruta solo estaba acotada por el coste de bcrypt.
 */
it('bloquea el login tras varios intentos fallidos', function () {
    User::factory()->create(['email' => 'correcta@fefuart.test']);

    foreach (range(1, 5) as $intento) {
        $this->postJson(route('auth.login'), [
            'email' => 'correcta@fefuart.test',
            'password' => 'no-es-esta',
        ])->assertStatus(422);
    }

    // El sexto intento ya no llega a comprobar la contrasena, ni siquiera
    // con la correcta: el limitador por email+IP lo corta antes.
    $this->postJson(route('auth.login'), [
        'email' => 'correcta@fefuart.test',
        'password' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('no distingue entre email inexistente y contrasena incorrecta', function () {
    User::factory()->create(['email' => 'correcta@fefuart.test']);

    $conEmailReal = $this->postJson(route('auth.login'), [
        'email' => 'correcta@fefuart.test',
        'password' => 'no-es-esta',
    ]);

    $conEmailFalso = $this->postJson(route('auth.login'), [
        'email' => 'no-existe@fefuart.test',
        'password' => 'no-es-esta',
    ]);

    // Mismo status y mismo mensaje: el login no sirve de oraculo de cuentas.
    expect($conEmailFalso->json('message'))->toBe($conEmailReal->json('message'));
});

it('cierra la sesion y deja de dar acceso', function () {
    User::factory()->create(['email' => 'correcta@fefuart.test']);

    // Sesion real via endpoint, no actingAs(): actingAs fija el usuario en el
    // proceso de test, asi que sobrevive al logout y no probaria nada.
    $this->postJson(route('auth.login'), [
        'email' => 'correcta@fefuart.test',
        'password' => 'password',
    ])->assertOk();

    $this->getJson(route('auth.me'))->assertOk();

    $this->postJson(route('auth.logout'))->assertNoContent();

    // El guard `sanctum` queda cacheado en el contenedor, que en tests
    // persiste entre peticiones; en produccion cada peticion arranca limpia.
    // Sin esto, `me` seguiria respondiendo 200 con el usuario cacheado aunque
    // la sesion ya este destruida.
    $this->app['auth']->forgetGuards();

    // La comprobacion que importa: la cookie ya no abre rutas protegidas.
    expect(auth()->guard('web')->check())->toBeFalse();
    $this->getJson(route('auth.me'))->assertUnauthorized();
});

it('devuelve 401 en me sin sesion', function () {
    $this->getJson(route('auth.me'))->assertUnauthorized();
});

it('devuelve el usuario autenticado en me', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)->getJson(route('auth.me'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.role', 'admin');
});
