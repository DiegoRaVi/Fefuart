<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * Sanctum en modo SPA solo trata como stateful las peticiones que llegan de
 * un dominio declarado en SANCTUM_STATEFUL_DOMAINS, y lo decide leyendo la
 * cabecera Referer. Sin ella no se arranca la sesion y `$request->session()`
 * revienta. Un navegador siempre la envia; el cliente de tests no.
 */
beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));
});

it('registra un usuario y abre sesion', function () {
    $response = $this->postJson(route('auth.register'), [
        'name' => 'Felicitas',
        'email' => 'felicitas@fefuart.test',
        'password' => 'contrasena-larga',
        'password_confirmation' => 'contrasena-larga',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'felicitas@fefuart.test')
        ->assertJsonPath('data.role', 'customer');

    $this->assertAuthenticated();
});

/**
 * SEC-001 — regresion.
 *
 * En v1, AuthController leia `$request->get('role', 'user')` y `role` era
 * fillable, asi que esta misma peticion creaba un administrador contra un
 * endpoint publico y sin autenticacion previa.
 *
 * Se envian las dos formas del ataque: la de v1 (`role` por nombre) y la
 * que abriria el cambio a entero de D23 (`role_id` por id).
 */
it('ignora el rol enviado por el cliente al registrarse', function () {
    $this->postJson(route('auth.register'), [
        'name' => 'Intruso',
        'email' => 'intruso@fefuart.test',
        'password' => 'contrasena-larga',
        'password_confirmation' => 'contrasena-larga',
        'role' => 'admin',
        'role_id' => 2,
    ])->assertCreated()->assertJsonPath('data.role', 'customer');

    $user = User::where('email', 'intruso@fefuart.test')->sole();

    expect($user->role_id)->toBe(UserRole::Customer)
        ->and($user->isAdmin())->toBeFalse();
});

it('rechaza contrasenas mas cortas de ocho caracteres', function () {
    // v1 aceptaba min:5.
    $this->postJson(route('auth.register'), [
        'name' => 'Corta',
        'email' => 'corta@fefuart.test',
        'password' => 'abc123',
        'password_confirmation' => 'abc123',
    ])->assertStatus(422)->assertJsonValidationErrors('password');

    $this->assertGuest();
});

it('rechaza un email ya registrado', function () {
    User::factory()->create(['email' => 'repetido@fefuart.test']);

    $this->postJson(route('auth.register'), [
        'name' => 'Duplicado',
        'email' => 'repetido@fefuart.test',
        'password' => 'contrasena-larga',
        'password_confirmation' => 'contrasena-larga',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('no expone la contrasena en la respuesta', function () {
    $response = $this->postJson(route('auth.register'), [
        'name' => 'Felicitas',
        'email' => 'hash@fefuart.test',
        'password' => 'contrasena-larga',
        'password_confirmation' => 'contrasena-larga',
    ]);

    expect($response->json('data'))
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token');
});

/**
 * N19 — verificacion de email **al registrarse**.
 *
 * Lo encontro el E2E de la Fase 7, no un test de estos: la bateria cubria el
 * reenvio manual (`verification.send`) y el cambio de correo desde el
 * perfil, pero nadie habia comprobado el alta. El resultado era que quien se
 * registraba veia «tu correo todavia no esta verificado» sin haber recibido
 * nada que pinchar, y tenia que descubrir por su cuenta el boton de
 * reenviar.
 */
it('manda el correo de verificacion al registrarse', function () {
    Notification::fake();

    $this->postJson(route('auth.register'), [
        'name' => 'Marta Ruiz',
        'email' => 'marta@fefuart.test',
        'password' => 'unaclavelarga',
        'password_confirmation' => 'unaclavelarga',
    ])->assertCreated();

    Notification::assertSentTo(
        User::where('email', 'marta@fefuart.test')->sole(),
        VerifyEmail::class,
    );
});

/** Una cuenta recien creada no esta verificada: el enlace es lo que verifica. */
it('deja la cuenta sin verificar hasta que se pincha el enlace', function () {
    $this->postJson(route('auth.register'), [
        'name' => 'Marta Ruiz',
        'email' => 'marta@fefuart.test',
        'password' => 'unaclavelarga',
        'password_confirmation' => 'unaclavelarga',
    ])->assertCreated();

    expect(User::where('email', 'marta@fefuart.test')->sole()->email_verified_at)->toBeNull();
});
