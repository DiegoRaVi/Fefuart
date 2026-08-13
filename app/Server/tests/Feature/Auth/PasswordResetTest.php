<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));
    Notification::fake();
});

it('envia el enlace de recuperacion', function () {
    $user = User::factory()->create(['email' => 'olvido@fefuart.test']);

    $this->postJson(route('password.email'), ['email' => 'olvido@fefuart.test'])
        ->assertOk();

    Notification::assertSentTo($user, ResetPassword::class);
});

/**
 * El enlace debe llevar a la SPA, no a una ruta del backend: es React quien
 * muestra el formulario y luego llama a POST /api/auth/reset-password.
 */
it('apunta el enlace a la SPA', function () {
    $user = User::factory()->create(['email' => 'olvido@fefuart.test']);

    $this->postJson(route('password.email'), ['email' => 'olvido@fefuart.test']);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, (string) config('app.frontend_url'))
            && str_contains($url, 'token=');
    });
});

/**
 * Mismo comportamiento exista o no la cuenta. Distinguirlas convertiria el
 * endpoint en un oraculo de que direcciones estan registradas.
 */
it('responde igual con un email desconocido', function () {
    $conCuenta = User::factory()->create(['email' => 'existe@fefuart.test']);

    $real = $this->postJson(route('password.email'), ['email' => 'existe@fefuart.test']);
    $falso = $this->postJson(route('password.email'), ['email' => 'no-existe@fefuart.test']);

    expect($falso->status())->toBe($real->status())
        ->and($falso->json('message'))->toBe($real->json('message'));

    Notification::assertSentTo($conCuenta, ResetPassword::class);
});

it('restablece la contrasena con un token valido', function () {
    $user = User::factory()->create(['email' => 'olvido@fefuart.test']);

    $this->postJson(route('password.email'), ['email' => 'olvido@fefuart.test']);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $this->postJson(route('password.store'), [
            'token' => $notification->token,
            'email' => 'olvido@fefuart.test',
            'password' => 'contrasena-nueva',
            'password_confirmation' => 'contrasena-nueva',
        ])->assertOk();

        return true;
    });

    // La comprobacion que importa: la contrasena nueva abre sesion.
    $this->postJson(route('auth.login'), [
        'email' => 'olvido@fefuart.test',
        'password' => 'contrasena-nueva',
    ])->assertOk();
});

it('rechaza un token invalido', function () {
    User::factory()->create(['email' => 'olvido@fefuart.test']);

    $this->postJson(route('password.store'), [
        'token' => 'token-inventado',
        'email' => 'olvido@fefuart.test',
        'password' => 'contrasena-nueva',
        'password_confirmation' => 'contrasena-nueva',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});
