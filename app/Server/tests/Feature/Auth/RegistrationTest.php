<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        ->assertJsonPath('data.role', 'user');

    $this->assertAuthenticated();
});

/**
 * SEC-001 — regresion.
 *
 * En v1, AuthController leia `$request->get('role', 'user')` y `role` era
 * fillable, asi que esta misma peticion creaba un administrador contra un
 * endpoint publico y sin autenticacion previa.
 */
it('ignora el rol enviado por el cliente al registrarse', function () {
    $this->postJson(route('auth.register'), [
        'name' => 'Intruso',
        'email' => 'intruso@fefuart.test',
        'password' => 'contrasena-larga',
        'password_confirmation' => 'contrasena-larga',
        'role' => 'admin',
    ])->assertCreated()->assertJsonPath('data.role', 'user');

    $user = User::where('email', 'intruso@fefuart.test')->sole();

    expect($user->role)->toBe(UserRole::User)
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
