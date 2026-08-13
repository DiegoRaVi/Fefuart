<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

/**
 * P5 — el backend habla el idioma del cliente.
 *
 * Salio al montar los formularios de la SPA en la Fase 3: la validacion
 * devolvia «The password field must be at least 8 characters.» y el correo
 * de recuperacion llegaba con el asunto «Reset Password Notification». Esos
 * textos no se quedan en un log: se pintan tal cual delante de quien esta
 * encargando un dibujo, en una web en espanol.
 */
beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));
});

it('esta configurado en espanol', function () {
    expect(config('app.locale'))->toBe('es');
});

it('devuelve los errores de validacion en espanol', function () {
    $respuesta = $this->postJson(route('auth.register'), [
        'name' => 'Corta',
        'email' => 'corta@fefuart.test',
        'password' => 'abc12',
        'password_confirmation' => 'abc12',
    ])->assertStatus(422);

    expect($respuesta->json('errors.password.0'))
        ->toContain('al menos')
        ->not->toContain('must be at least');
});

/**
 * No basta con traducir la plantilla del mensaje: si el nombre del campo se
 * queda en ingles sale «El campo password debe...», que es peor que no
 * traducir nada porque parece un fallo.
 */
it('traduce tambien el nombre del campo', function () {
    $respuesta = $this->postJson(route('auth.register'), [
        'name' => 'Sin correo',
        'password' => 'contrasena-larga',
        'password_confirmation' => 'contrasena-larga',
    ])->assertStatus(422);

    expect($respuesta->json('errors.email.0'))->toContain('correo electr');
});

it('envia el correo de recuperacion con el asunto en espanol', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'olvido@fefuart.test']);

    $this->postJson(route('password.email'), ['email' => 'olvido@fefuart.test']);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $asunto = $notification->toMail($user)->subject;

        return str_contains(mb_strtolower((string) $asunto), 'contrase');
    });
});
